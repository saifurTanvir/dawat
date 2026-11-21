<?php

namespace Tests\Feature\Geo;

use App\Enums\KitchenStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\User;
use App\Services\Geo\FocusSplit;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocusSplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_badda_and_the_rest_are_counted_apart_and_shown_on_the_dashboard(): void
    {
        $badda = Area::query()->where('name', 'মধ্য বাড্ডা')->first() ?? Area::factory()->create(['name' => 'মধ্য বাড্ডা']);
        $other = Area::query()->where('name', 'মিরপুর ১০')->first() ?? Area::factory()->create(['name' => 'মিরপুর ১০']);
        Kitchen::factory()->create(['area_id' => $badda->id, 'status' => KitchenStatus::Approved]);
        Kitchen::factory()->count(2)->create(['area_id' => $other->id, 'status' => KitchenStatus::Approved]);

        $c = app(FocusSplit::class)->counts();
        $this->assertSame(1, $c['badda']['cooks']);
        $this->assertSame(2, $c['rest']['cooks']);
        $this->assertSame(1, $c['badda']['cooks_today']);

        $admin = User::factory()->create();
        $admin->assignRole(Roles::ADMIN);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('বাড্ডা বনাম বাকি ঢাকা');

        $this->artisan('ops:pin-stats')->expectsOutputToContain('BADDA: cooks 1')->assertSuccessful();
    }
}
