<?php

namespace Tests\Feature\Regular;

use App\Actions\PairCustomer;
use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\Resources\Companies\Pages\ManageCompanies;
use App\Jobs\SendOfficeDropCode;
use App\Models\Area;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\OfficeDrop;
use App\Models\Order;
use App\Models\Post;
use App\Models\Sticker;
use App\Models\User;
use App\Services\Orders\BookingPlanner;
use App\Services\Regular\PairingSuggester;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** F68: an office's employees' boxes travel as one drop, handed to its contact once. */
class OfficeDropTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $shahidar;

    private User $admin;

    private User $contact;

    private Company $office;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dawat.orders.sms' => false]);
        $this->travelTo(Carbon::parse('2026-11-18 11:00', 'Asia/Dhaka'));
        $this->area = Area::factory()->create();
        $this->admin = User::factory()->admin()->create();
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->shahidar->shahidarAreas()->attach($this->area);
        $this->contact = User::factory()->create(['name' => 'রিসেপশন রিনা']);
        $this->office = Company::create(['name' => 'ABC সফটওয়্যার', 'address' => 'লেভেল ৫, বাড়ি ১২', 'area_id' => $this->area->id, 'contact_user_id' => $this->contact->id]);
    }

    private function employee(string $name, Kitchen $kitchen, ?Company $company = null): Customer
    {
        $c = Customer::factory()->create(['area_id' => $this->area->id, 'company_id' => ($company ?? $this->office)->id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        $c->user->update(['name' => $name]);
        app(PairCustomer::class)($c, $kitchen, $this->shahidar, $this->admin);

        return $c;
    }

    private function cookPosts(Kitchen $kitchen, int $plates = 6): Post
    {
        $post = Post::factory()->create(['kitchen_id' => $kitchen->id, 'meal_slot' => MealSlot::Lunch, 'plates_total' => $plates, 'plates_left' => $plates]);
        app(BookingPlanner::class)->bookForPost($post);

        return $post;
    }

    public function test_one_cook_can_feed_several_employees_of_one_office_but_not_strangers(): void
    {
        $cook = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $cook);
        $this->employee('বীথি', $cook);
        $this->assertSame(2, $cook->pairings()->count());

        // Still one cook, one customer for everyone else.
        $stranger = Customer::factory()->create(['area_id' => $this->area->id, 'status' => CustomerStatus::Waiting]);
        $this->expectException(ValidationException::class);
        app(PairCustomer::class)($stranger, $cook, $this->shahidar, $this->admin);
    }

    public function test_the_offices_own_cook_is_suggested_first(): void
    {
        $cook = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $cook);
        $next = Customer::factory()->create(['area_id' => $this->area->id, 'company_id' => $this->office->id, 'status' => CustomerStatus::Waiting]);

        $this->assertSame($cook->id, app(PairingSuggester::class)->suggestCook($next)['kitchen']->id);
        $loner = Customer::factory()->create(['area_id' => $this->area->id, 'status' => CustomerStatus::Waiting]);
        $this->assertNotContains($cook->id, app(PairingSuggester::class)->freeCooks($loner)->pluck('kitchen.id'));
    }

    public function test_boxes_join_one_drop_and_the_contact_gets_one_code(): void
    {
        Queue::fake();
        $a = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $b = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $a);
        $this->employee('বীথি', $a);
        $this->employee('চন্দন', $b);
        $this->cookPosts($a);
        $this->cookPosts($b);

        $this->assertSame(1, OfficeDrop::count());
        $drop = OfficeDrop::first();
        $this->assertSame(3, $drop->orders()->count());
        Queue::assertPushed(SendOfficeDropCode::class, 1);

        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/today')->assertOk()
            ->assertJsonPath('data.office_drops.0.company', 'ABC সফটওয়্যার')
            ->assertJsonPath('data.office_drops.0.boxes', 3)
            ->assertJsonPath('data.office_drops.0.booked', 3)
            ->assertJsonPath('data.office_drops.0.contact.name', 'রিসেপশন রিনা')
            ->assertJsonCount(2, 'data.office_drops.0.kitchens')
            ->assertJsonPath('data.orders.0.office_drop_id', $drop->id);
    }

    public function test_pickup_per_cook_with_one_bag_sticker_then_one_code_hands_it_all_over(): void
    {
        $a = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $b = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $a);
        $this->employee('বীথি', $a);
        $this->employee('চন্দন', $b);
        $this->cookPosts($a);
        $this->cookPosts($b);
        $drop = OfficeDrop::first();
        $drop->forceFill(['code_hash' => Hash::make('7788')])->save();
        Sticker::create(['code' => 'BAGA2222', 'batch' => 't']);

        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $a->id, 'sticker' => 'BAGA2222'])
            ->assertOk()->assertJsonPath('data.picked', 2)->assertJsonPath('data.booked', 1)->assertJsonPath('data.bags.0', 'BAGA 2222');
        $this->assertSame($drop->id, Sticker::where('code', 'BAGA2222')->value('office_drop_id'));

        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/deliver", ['code' => '1111'])->assertStatus(422);
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/deliver", ['code' => '7788'])->assertOk()->assertJsonPath('data.delivered', 2);
        $this->assertNull($drop->fresh()->delivered_at); // one cook's boxes still to come

        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $b->id])->assertOk();
        $this->postJson("/api/v1/shahidar/drops/{$drop->id}/deliver", ['code' => '7788'])->assertOk()->assertJsonPath('data.done', true);
        $this->assertSame(3, Order::where('status', OrderStatus::Delivered)->where('confirm_method', 'office_code')->count());
        $this->assertSame('office_code', $drop->fresh()->confirm_method);
    }

    public function test_the_contact_scans_the_bag_and_takes_the_drop(): void
    {
        $a = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $a);
        $this->employee('বীথি', $a);
        $this->cookPosts($a);
        $drop = OfficeDrop::first();
        Sticker::create(['code' => 'BAGB3333', 'batch' => 't']);
        $this->actingAs($this->shahidar, 'sanctum')->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $a->id, 'sticker' => 'BAGB3333'])->assertOk();

        // Strangers and employees learn nothing; the shahidar's scan points to the drop card.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/stickers/BAGB3333')->assertExactJson(['data' => ['state' => 'office']]);
        $this->actingAs($this->shahidar, 'sanctum')->getJson('/api/v1/shahidar/stickers/BAGB3333')->assertStatus(422);

        $this->actingAs($this->contact, 'sanctum')->getJson('/api/v1/stickers/BAGB3333')
            ->assertJsonPath('data.state', 'office_contact')->assertJsonPath('data.drop.picked', 2)->assertJsonPath('data.drop.can_confirm', true);
        $this->postJson('/api/v1/stickers/BAGB3333/received')->assertOk();
        $this->assertSame('office_scan', $drop->fresh()->confirm_method);
        $this->assertSame(2, Order::where('confirm_method', 'office_scan')->count());
    }

    public function test_other_shahidars_cannot_touch_the_drop_and_regular_customers_are_unaffected(): void
    {
        $a = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->employee('আনিস', $a);
        $this->cookPosts($a);
        $drop = OfficeDrop::first();
        $other = User::factory()->create();
        $other->assignRole(Roles::SHAHIDAR);
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/shahidar/drops/{$drop->id}/pickup", ['kitchen_id' => $a->id])->assertNotFound();

        $solo = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $home = Customer::factory()->create(['area_id' => $this->area->id, 'status' => CustomerStatus::Waiting, 'meal_slots' => ['lunch'], 'weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        app(PairCustomer::class)($home, $solo, $this->shahidar, $this->admin);
        $this->cookPosts($solo);
        $this->assertNull(Order::where('customer_id', $home->id)->value('office_drop_id'));
        $this->assertSame(1, OfficeDrop::count());
    }

    public function test_admin_adds_an_office_with_its_contact(): void
    {
        $this->actingAs($this->admin, 'web');
        Livewire::test(ManageCompanies::class)->callAction('create', [
            'name' => 'XYZ ব্যাংক', 'area_id' => $this->area->id, 'address' => 'তলা ৩',
            'contact_name' => 'জামিল', 'contact_phone' => '০১৮১২৩৪৫৬৭৮', 'drop_note' => 'দুপুর ১টার আগে', 'is_active' => true,
        ])->assertHasNoActionErrors();

        $company = Company::where('name', 'XYZ ব্যাংক')->firstOrFail();
        $this->assertSame('01812345678', $company->contact->phone);
        $this->assertSame('জামিল', $company->contact->name);
        $this->get('/admin/companies')->assertOk()->assertSee('XYZ ব্যাংক');
    }
}
