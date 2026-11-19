<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Areas\AreaResource;
use App\Filament\Resources\CallRequests\CallRequestResource;
use App\Filament\Resources\Kitchens\KitchenResource;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Filament\Resources\Staff\StaffResource;
use App\Filament\Resources\StaffRoles\Pages\ManageStaffRoles;
use App\Filament\Resources\StaffRoles\StaffRoleResource;
use App\Filament\Widgets\DawatStats;
use App\Filament\Widgets\FollowUps;
use App\Models\Remark;
use App\Models\User;
use App\Services\Admin\Impersonate;
use App\Services\Admin\StaffView;
use App\Support\AdminFeatures;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** F69: staff roles made of panel features; staff see only those. */
class StaffRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function makeCallTeam(): User
    {
        $this->actingAs($this->admin, 'web');
        Livewire::test(ManageStaffRoles::class)->callAction('create', ['name' => 'কল টিম', 'features' => ['contacts']])->assertHasNoActionErrors();
        $role = Role::where('name', 'কল টিম')->firstOrFail();
        $this->assertSame(['contacts'], StaffRoleResource::featuresOf($role));

        Livewire::test(ManageStaff::class)->callAction('create', [
            'name' => 'সুমি', 'phone' => '01912345678', 'email' => 'Sumi@Dawat.test', 'password' => 'call-team-2026', 'staff_role' => $role->id,
        ])->assertHasNoActionErrors();

        return User::where('phone', '01912345678')->firstOrFail();
    }

    public function test_admin_makes_a_role_and_a_staff_member_who_can_log_in(): void
    {
        $staff = $this->makeCallTeam();

        $this->assertSame('sumi@dawat.test', $staff->email);
        $this->assertTrue(Hash::check('call-team-2026', $staff->password));
        $this->assertTrue($staff->isStaff());
        $this->assertTrue($staff->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($staff->canUseFeature('contacts'));
        $this->assertFalse($staff->canUseFeature('money'));

        // An ordinary app user still cannot.
        $this->assertFalse(User::factory()->create()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_staff_see_only_their_features(): void
    {
        $staff = $this->makeCallTeam();
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff, 'web');

        $this->get('/admin')->assertOk();
        $this->get(CallRequestResource::getUrl())->assertOk();
        $this->get(KitchenResource::getUrl())->assertForbidden();
        $this->get(AreaResource::getUrl())->assertForbidden();
        $this->get(StaffResource::getUrl())->assertForbidden();
        $this->get(StaffRoleResource::getUrl())->assertForbidden();
        $this->get('/admin/money')->assertForbidden();

        $this->assertTrue(AdminFeatures::allows(FollowUps::class));
        $this->assertFalse(DawatStats::canView());
        $this->get('/admin')->assertDontSee('গ্রাহক যোগ করুন'); // quick-add is "নতুন মানুষ যোগ"
    }

    public function test_admin_only_tools_stay_with_admins(): void
    {
        $staff = $this->makeCallTeam();
        $person = User::factory()->create();

        $this->assertFalse(Impersonate::canOpen($staff, $person));
        $this->assertFalse(Impersonate::canOpen($this->admin, $staff)); // nobody opens a team account
    }

    public function test_revoking_ends_panel_access_and_roles_can_change(): void
    {
        $staff = $this->makeCallTeam();
        $this->actingAs($this->admin, 'web');
        Livewire::test(ManageStaffRoles::class)->callAction('create', ['name' => 'টাকার টিম', 'features' => ['money', 'orders']]);
        $money = Role::where('name', 'টাকার টিম')->firstOrFail();

        Livewire::test(ManageStaff::class)->callTableAction('edit', $staff, ['name' => 'সুমি', 'email' => 'sumi@dawat.test', 'password' => '', 'staff_role' => $money->id])
            ->assertHasNoTableActionErrors();
        $staff = $staff->fresh();
        $this->assertTrue($staff->canUseFeature('money'));
        $this->assertFalse($staff->canUseFeature('contacts'));
        $this->assertTrue(Hash::check('call-team-2026', $staff->password)); // blank keeps it

        Livewire::test(ManageStaff::class)->callTableAction('revoke', $staff);
        $this->assertFalse($staff->fresh()->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_reserved_role_names_are_refused(): void
    {
        $this->actingAs($this->admin, 'web');
        Livewire::test(ManageStaffRoles::class)->callAction('create', ['name' => 'admin', 'features' => ['contacts']])
            ->assertHasActionErrors(['name']);
    }

    public function test_a_remark_can_close_open_follow_ups_and_close_takes_a_note(): void
    {
        $person = User::factory()->create();
        $old = Remark::create(['user_id' => $person->id, 'body' => 'OTP দেননি', 'follow_up_on' => now()->toDateString(), 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin, 'web');

        Livewire::test(FollowUps::class)
            ->callTableAction('addRemark', $old, ['body' => 'ফোনে কথা হলো, কাল থেকে নেবেন', 'close_open' => true])
            ->assertHasNoTableActionErrors();
        $old->refresh();
        $this->assertNotNull($old->done_at);
        $this->assertSame('ফোনে কথা হলো, কাল থেকে নেবেন', $old->done_note);
        $this->assertSame(0, Remark::query()->due()->count());

        $next = Remark::create(['user_id' => $person->id, 'body' => 'আবার ফোন', 'follow_up_on' => now()->toDateString(), 'created_by' => $this->admin->id]);
        Livewire::test(FollowUps::class)->callTableAction('close', $next, ['note' => 'নম্বর বন্ধ, বাদ'])->assertHasNoTableActionErrors();
        $this->assertSame('নম্বর বন্ধ, বাদ', $next->fresh()->done_note);
        $this->assertSame($this->admin->id, $next->fresh()->done_by);
    }

    public function test_a_staff_member_can_be_a_full_admin_clone_and_back(): void
    {
        $staff = $this->makeCallTeam();
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageStaff::class)->callTableAction('edit', $staff, ['name' => 'সুমি', 'email' => 'sumi@dawat.test', 'password' => '', 'staff_role' => StaffResource::FULL_ADMIN])
            ->assertHasNoTableActionErrors();
        $staff = $staff->fresh();
        $this->assertTrue($staff->isAdmin());
        $this->assertFalse($staff->isStaff()); // the old limited role is gone
        $this->assertTrue(AdminFeatures::allows(StaffResource::class, $staff));

        // Back to a limited role; and the last admin can never be removed.
        Livewire::test(ManageStaff::class)->callTableAction('edit', $staff, ['name' => 'সুমি', 'email' => 'sumi@dawat.test', 'password' => '', 'staff_role' => Role::where('name', 'কল টিম')->value('id')])
            ->assertHasNoTableActionErrors();
        $this->assertFalse($staff->fresh()->isAdmin());
        Livewire::test(ManageStaff::class)->assertTableActionHidden('revoke', $this->admin);
    }

    public function test_new_staff_can_be_made_full_admin_from_the_start(): void
    {
        $this->actingAs($this->admin, 'web');
        Livewire::test(ManageStaff::class)->callAction('create', [
            'name' => 'ম্যানেজার', 'phone' => '01922222222', 'email' => 'manager@dawat.test', 'password' => 'manager-2026', 'staff_role' => StaffResource::FULL_ADMIN,
        ])->assertHasNoActionErrors();
        $this->assertTrue(User::where('phone', '01922222222')->firstOrFail()->isAdmin());
    }

    public function test_admin_looks_at_the_panel_as_a_staff_member_and_comes_back(): void
    {
        $staff = $this->makeCallTeam();
        $this->actingAs($this->admin, 'web');

        Livewire::test(ManageStaff::class)->assertTableActionHidden('viewAs', $this->admin)->callTableAction('viewAs', $staff);
        $this->assertSame($staff->id, auth('web')->id());
        $this->get('/admin')->assertOk()->assertSee('হিসেবে দেখছেন')->assertSee('অ্যাডমিনে ফিরুন');
        $this->get(KitchenResource::getUrl())->assertForbidden();

        $this->get('/admin-tools/staff-view/stop')->assertRedirect('/admin/staff');
        $this->assertSame($this->admin->id, auth('web')->id());
        $this->get('/admin')->assertOk()->assertDontSee('হিসেবে দেখছেন');
    }

    public function test_staff_cannot_use_view_as_or_the_way_back_to_become_admin(): void
    {
        $staff = $this->makeCallTeam();
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff, 'web');
        $this->expectException(ValidationException::class);
        StaffView::start($staff, User::factory()->create());
    }

    public function test_the_way_back_does_nothing_without_a_started_view(): void
    {
        $staff = $this->makeCallTeam();
        $this->app['auth']->forgetGuards();
        $this->actingAs($staff, 'web');
        $this->withSession([StaffView::KEY => ['admin' => $this->admin->id, 'staff' => 999, 'name' => 'x']])
            ->get('/admin-tools/staff-view/stop')->assertRedirect('/admin');
        $this->assertSame($staff->id, auth('web')->id());
    }
}
