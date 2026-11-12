<?php

namespace Tests\Feature\Shahidar;

use App\Enums\ShahidarApplicationStatus as Status;
use App\Filament\Resources\ShahidarApplications\Pages\ListShahidarApplications;
use App\Filament\Resources\ShahidarApplications\Pages\ViewShahidarApplication;
use App\Jobs\SendWebPush;
use App\Models\Area;
use App\Models\ShahidarApplication;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ShahidarApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        $areas = Area::factory()->count(2)->create(['is_active' => true]);

        return [
            'name' => 'রাকিব হাসান',
            'phone' => '০১৭১২৩৪৫৬৭৮',
            'gender' => 'male',
            'age' => 24,
            'lives_in' => 'মধ্য বাড্ডা',
            'area_ids' => $areas->pluck('id')->all(),
            'job' => 'student',
            'job_note' => 'সকালে ক্লাস',
            'vehicle' => 'bicycle',
            'has_license' => true, // ignored: a bicycle needs none
            'has_smartphone' => true,
            'has_nid' => true,
            'hours' => 'part_time',
            'slots' => ['lunch', 'night'],
            'days_per_week' => 6,
            'experience' => null,
            'heard_from' => 'facebook',
            'note' => null,
            'agree' => true,
            ...$overrides,
        ];
    }

    public function test_anyone_can_apply_and_admins_are_told(): void
    {
        Queue::fake();
        config(['services.webpush.public_key' => 'x', 'services.webpush.private_key' => 'y']);
        User::factory()->admin()->create();

        $this->postJson('/api/v1/shahidar-applications', $this->form())
            ->assertCreated()->assertJsonPath('is_new', true);

        $app = ShahidarApplication::sole();
        $this->assertSame('01712345678', $app->phone);
        $this->assertSame(Status::New, $app->status);
        $this->assertNull($app->has_license);
        $this->assertSame(['lunch', 'night'], $app->slots);
        $this->assertCount(2, $app->areas);
        Queue::assertPushed(SendWebPush::class);
    }

    public function test_options_carry_the_income_offer(): void
    {
        $this->getJson('/api/v1/shahidar-applications/options')
            ->assertOk()
            ->assertJsonPath('data.income.min', 20000)
            ->assertJsonPath('data.income.max', 30000)
            ->assertJsonPath('data.gender.1.label', 'মহিলা');
    }

    public function test_required_fields_and_licence_for_motorbikes(): void
    {
        $this->postJson('/api/v1/shahidar-applications', ['agree' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'gender', 'lives_in', 'area_ids', 'job', 'vehicle', 'has_smartphone', 'has_nid', 'hours', 'slots', 'days_per_week', 'agree']);

        $this->postJson('/api/v1/shahidar-applications', $this->form(['vehicle' => 'motorbike', 'has_license' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['has_license']);
    }

    public function test_second_form_from_same_number_updates_the_open_one(): void
    {
        $this->postJson('/api/v1/shahidar-applications', $this->form())->assertCreated();
        $this->postJson('/api/v1/shahidar-applications', $this->form(['phone' => '01712345678', 'vehicle' => 'motorbike', 'has_license' => true]))
            ->assertOk()->assertJsonPath('is_new', false);

        $this->assertSame(1, ShahidarApplication::count());
        $this->assertSame('motorbike', ShahidarApplication::sole()->vehicle);
        $this->assertTrue(ShahidarApplication::sole()->has_license);
    }

    public function test_an_existing_shahidar_is_told_to_sign_in(): void
    {
        User::factory()->create(['phone' => '01712345678'])->assignRole(Roles::SHAHIDAR);

        $this->postJson('/api/v1/shahidar-applications', $this->form())
            ->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_admin_lists_marks_contacted_and_onboards(): void
    {
        $this->postJson('/api/v1/shahidar-applications', $this->form());
        $app = ShahidarApplication::sole();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ListShahidarApplications::class)->assertCanSeeTableRecords([$app]);

        Livewire::test(ViewShahidarApplication::class, ['record' => $app->id])
            ->callAction('contacted', ['note' => 'কাল থেকে শুরু'])
            ->assertNotified();
        $this->assertSame(Status::Contacted, $app->fresh()->status);
        $this->assertSame($admin->id, $app->fresh()->handled_by);

        $areaId = $app->areas->first()->id;
        Livewire::test(ViewShahidarApplication::class, ['record' => $app->id])
            ->callAction('onboard', ['name' => 'রাকিব', 'area_ids' => [$areaId]])
            ->assertHasNoActionErrors();

        $app->refresh();
        $this->assertSame(Status::Onboarded, $app->status);
        $user = $app->user;
        $this->assertTrue($user->isShahidar());
        $this->assertSame('01712345678', $user->phone);
        $this->assertSame([$areaId], $user->shahidarAreas()->pluck('areas.id')->all());
    }
}
