<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerStatus;
use App\Enums\MealSlot;
use App\Enums\OrderStatus;
use App\Filament\RelationManagers\CustomerOrdersRelationManager;
use App\Filament\RelationManagers\KitchenOrdersRelationManager;
use App\Filament\RelationManagers\PostsRelationManager;
use App\Filament\RelationManagers\ShahidarOrdersRelationManager;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shahidars\Pages\ViewShahidar;
use App\Filament\Resources\Shahidars\ShahidarResource;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Order;
use App\Models\Pairing;
use App\Models\Post;
use App\Models\User;
use App\Services\Orders\Delivery;
use App\Services\Orders\Settlements;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** F49: the admin sees everyone's history and can open any box from it. */
class HistoryTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    private User $shahidar;

    private Customer $customer;

    private Order $order;

    private Order $old;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 14:00', 'Asia/Dhaka'));
        $this->actingAs(User::factory()->admin()->create());
        $this->kitchen = Kitchen::factory()->approved()->create(['display_name' => 'সুমার রান্নাঘর']);
        $this->shahidar = User::factory()->create(['name' => 'করিম']);
        $this->shahidar->assignRole(Roles::SHAHIDAR);
        $this->customer = Customer::factory()->create(['area_id' => $this->kitchen->area_id, 'status' => CustomerStatus::Active]);
        $this->customer->user->update(['name' => 'কল্যাণ']);
        Pairing::create(['customer_id' => $this->customer->id, 'kitchen_id' => $this->kitchen->id, 'shahidar_id' => $this->shahidar->id, 'started_at' => now()->subDays(60)]);
        $post = Post::factory()->create(['kitchen_id' => $this->kitchen->id, 'title' => 'ভাত, মাছ']);

        $make = fn (string $date, int $seq, OrderStatus $status) => Order::create([
            'service_date' => $date, 'seq' => $seq, 'meal_slot' => MealSlot::Lunch, 'kitchen_id' => $this->kitchen->id, 'post_id' => $post->id,
            'customer_id' => $this->customer->id, 'shahidar_id' => $this->shahidar->id, 'plates' => 1, 'food_paisa' => 15000,
            'delivery_fee_paisa' => 2000, 'status' => $status, 'title' => 'ভাত, মাছ',
        ]);
        $this->order = $make('2026-11-12', 1, OrderStatus::PickedUp);
        $this->old = $make('2026-09-01', 4, OrderStatus::Delivered);
        app(Delivery::class)->deliverByShahidar($this->order, $this->shahidar, null);
        app(Settlements::class)->markPaid($this->order->fresh(), $this->shahidar);
    }

    public function test_a_box_has_its_own_page_with_the_timeline_and_links(): void
    {
        $this->get(OrderResource::getUrl('view', ['record' => $this->order]))
            ->assertOk()
            ->assertSee('কী কী হলো')
            ->assertSee('নিয়মিত বক্স বুক হলো')
            ->assertSee('পৌঁছেছে')
            ->assertSee('টাকা পাওয়া গেছে')
            ->assertSee('কল্যাণ')
            ->assertSee('/admin/kitchens/'.$this->kitchen->id, false)
            ->assertSee('/admin/shahidars/'.$this->shahidar->id, false);
    }

    public function test_kitchen_customer_and_shahidar_pages_show_summaries_and_history(): void
    {
        $this->get("/admin/kitchens/{$this->kitchen->id}")->assertOk()->assertSee('সারাংশ')->assertSee('এখন পাওনা');
        $this->get("/admin/customers/{$this->customer->id}")->assertOk()->assertSee('"আজ লাগবে না"');
        $this->get("/admin/shahidars/{$this->shahidar->id}")->assertOk()->assertSee('নগদ তুলেছেন')->assertSee('এখন হাতে');

        // The last 30 days by default; older boxes need a wider date range.
        Livewire::test(KitchenOrdersRelationManager::class, ['ownerRecord' => $this->kitchen, 'pageClass' => ViewKitchen::class])
            ->assertCanSeeTableRecords([$this->order])->assertCanNotSeeTableRecords([$this->old])
            ->filterTable('dates', ['from' => '2026-08-01', 'until' => null])
            ->assertCanSeeTableRecords([$this->order, $this->old]);

        Livewire::test(CustomerOrdersRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords([$this->order]);
        Livewire::test(ShahidarOrdersRelationManager::class, ['ownerRecord' => $this->shahidar, 'pageClass' => ViewShahidar::class])
            ->assertCanSeeTableRecords([$this->order]);
        Livewire::test(PostsRelationManager::class, ['ownerRecord' => $this->kitchen, 'pageClass' => ViewKitchen::class])
            ->assertSee('ভাত, মাছ');
    }

    public function test_history_downloads_as_csv(): void
    {
        Livewire::test(KitchenOrdersRelationManager::class, ['ownerRecord' => $this->kitchen, 'pageClass' => ViewKitchen::class])
            ->callTableAction('csv')
            ->assertFileDownloaded('dawat-boxes-2026-11-12.csv');
    }

    public function test_admin_search_finds_a_box_by_number_and_people_by_phone(): void
    {
        $results = OrderResource::getGlobalSearchResults('১২-১');
        $this->assertCount(1, $results);
        $this->assertStringContainsString('/admin/orders/'.$this->order->id, $results->first()->url);
        $this->assertCount(0, OrderResource::getGlobalSearchResults('ভাত'));

        $this->assertNotEmpty(CustomerResource::getGlobalSearchResults($this->customer->user->phone));
        $this->assertNotEmpty(ShahidarResource::getGlobalSearchResults('করিম'));
    }
}
