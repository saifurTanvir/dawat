<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Kitchens\Pages\ListKitchens;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Shahidars\Pages\ViewShahidar;
use App\Filament\Widgets\FollowUps;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\Remark;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** F63: admins' remarks on people, with follow-ups on the dashboard. */
class RemarksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-14 22:00', 'Asia/Dhaka'));
        $this->admin = User::factory()->admin()->create(['name' => 'রাশেদ']);
        $this->actingAs($this->admin);
    }

    public function test_remark_on_a_cook_with_a_follow_up_shows_in_the_list_and_on_the_day(): void
    {
        $kitchen = Kitchen::factory()->create();

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])
            ->callAction('remark', ['body' => 'OTP দিয়ে যাচাই করেননি, কাল আবার ফোন', 'follow_up_on' => '2026-11-15'])
            ->assertHasNoActionErrors();

        $remark = Remark::sole();
        $this->assertSame($kitchen->user_id, $remark->user_id);
        $this->assertSame($this->admin->id, $remark->created_by);

        Livewire::test(ListKitchens::class)->assertSee('OTP দিয়ে যাচাই করেননি');

        // Not due yet today; due tomorrow.
        $this->assertFalse(FollowUps::canView());
        $this->travelTo(Carbon::parse('2026-11-15 09:00', 'Asia/Dhaka'));
        $this->assertTrue(FollowUps::canView());

        Livewire::test(FollowUps::class)
            ->assertCanSeeTableRecords([$remark])
            ->callTableAction('close', $remark);
        $this->assertNotNull($remark->fresh()->done_at);
        $this->assertFalse(FollowUps::canView());
    }

    public function test_remarks_on_customers_and_shahidars_and_the_history_shows(): void
    {
        $customer = Customer::factory()->create();
        Livewire::test(ListCustomers::class)
            ->callTableAction('remark', $customer, ['body' => 'দুপুরে ফোন ধরেন না'])
            ->assertHasNoTableActionErrors();
        $this->assertSame($customer->user_id, Remark::sole()->user_id);

        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        Livewire::test(ViewShahidar::class, ['record' => $shahidar->id])
            ->callAction('remark', ['body' => 'বাইক সারাতে দিয়েছেন'])
            ->assertHasNoActionErrors();
        $html = view('filament.remarks.history', ['remarks' => $shahidar->remarks()->with(['author', 'doneBy'])->get()])->render();
        $this->assertStringContainsString('বাইক সারাতে দিয়েছেন', $html);
        $this->assertStringContainsString('রাশেদ', $html);

        Livewire::test(ViewShahidar::class, ['record' => $shahidar->id])
            ->callAction('remark', ['body' => ''])
            ->assertHasActionErrors(['body' => 'required']);
    }

    public function test_dashboard_renders_with_follow_ups(): void
    {
        Remark::create(['user_id' => Kitchen::factory()->create()->user_id, 'body' => 'আজ ফোন', 'follow_up_on' => '2026-11-14', 'created_by' => $this->admin->id]);
        $this->get('/admin')->assertOk();
        Livewire::test(FollowUps::class)->assertSee('আজকের ফলো-আপ')->assertSee('আজ ফোন');
    }
}
