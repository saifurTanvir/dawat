<?php

namespace Tests\Feature\Regular;

use App\Filament\Resources\Shahidars\Pages\ManageShahidars;
use App\Models\Area;
use App\Models\User;
use App\Support\Roles;
use Filament\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShahidarAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_adds_a_shahidar_by_phone_with_areas(): void
    {
        $areas = Area::factory()->count(2)->create();

        Livewire::test(ManageShahidars::class)
            ->callAction(CreateAction::class, [
                'phone' => '০১৭১১-২২২৩৩৩',
                'name' => 'করিম ভাই',
                'area_ids' => $areas->pluck('id')->all(),
            ])
            ->assertHasNoActionErrors();

        $user = User::where('phone', '01711222333')->firstOrFail();
        $this->assertTrue($user->isShahidar());
        $this->assertTrue($user->hasRole(Roles::BUYER));
        $this->assertSame('করিম ভাই', $user->name);
        $this->assertEqualsCanonicalizing($areas->pluck('id')->all(), $user->shahidarAreas->pluck('id')->all());
        $this->assertCount(1, $areas->first()->shahidars);
    }

    public function test_an_existing_user_is_promoted_not_duplicated(): void
    {
        $existing = User::factory()->create(['phone' => '01811222333', 'name' => 'আগের নাম']);
        $area = Area::factory()->create();

        Livewire::test(ManageShahidars::class)
            ->callAction(CreateAction::class, ['phone' => '01811222333', 'name' => 'নতুন নাম', 'area_ids' => [$area->id]])
            ->assertHasNoActionErrors();

        $this->assertSame(1, User::where('phone', '01811222333')->count());
        $this->assertTrue($existing->fresh()->isShahidar());
        $this->assertSame('আগের নাম', $existing->fresh()->name);
    }

    public function test_bad_phone_is_refused(): void
    {
        $area = Area::factory()->create();

        Livewire::test(ManageShahidars::class)
            ->callAction(CreateAction::class, ['phone' => '12345', 'name' => 'x', 'area_ids' => [$area->id]])
            ->assertHasActionErrors(['phone']);
    }

    public function test_list_shows_only_shahidars_and_role_can_be_removed(): void
    {
        $shahidar = User::factory()->create();
        $shahidar->assignRole(Roles::SHAHIDAR);
        $shahidar->shahidarAreas()->attach(Area::factory()->create());
        $cook = User::factory()->cook()->create();

        Livewire::test(ManageShahidars::class)
            ->assertCanSeeTableRecords([$shahidar])
            ->assertCanNotSeeTableRecords([$cook])
            ->callTableAction('removeRole', $shahidar);

        $this->assertFalse($shahidar->fresh()->isShahidar());
        $this->assertCount(0, $shahidar->shahidarAreas()->get());
        $this->get('/admin/shahidars')->assertOk();
    }
}
