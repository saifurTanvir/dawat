<?php

namespace Tests\Feature\Kitchen;

use App\Models\Area;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Geo\GeoPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_areas_are_public_and_sorted_by_distance_when_asked(): void
    {
        Area::query()->delete(); // the F31 migration's service areas
        $mirpur = Area::factory()->create(['name' => 'মিরপুর', 'center' => GeoPoint::make(23.8069, 90.3687)]);
        $motijheel = Area::factory()->create(['name' => 'মতিঝিল', 'center' => GeoPoint::make(23.7330, 90.4172)]);
        Area::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/areas')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/areas?lat=23.73&lng=90.41')
            ->assertOk()
            ->assertJsonPath('data.0.id', $motijheel->id)
            ->assertJsonPath('data.1.id', $mirpur->id);
    }

    public function test_cook_sets_home_location_and_area(): void
    {
        $kitchen = Kitchen::factory()->notSetUp()->create();
        $area = Area::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['area_id' => $area->id, 'lat' => 23.80691, 'lng' => 90.36873])
            ->assertOk()
            ->assertJsonPath('data.has_location', true)
            ->assertJsonPath('data.is_set_up', true)
            ->assertJsonPath('data.area.id', $area->id)
            ->assertJsonMissingPath('data.lat')
            ->assertJsonMissingPath('data.location');

        $saved = $kitchen->fresh()->location;
        $this->assertEqualsWithDelta(23.80691, $saved->lat, 1e-6);
        $this->assertEqualsWithDelta(90.36873, $saved->lng, 1e-6);
    }

    public function test_location_outside_bangladesh_is_rejected(): void
    {
        $kitchen = Kitchen::factory()->notSetUp()->create();

        // lat/lng swapped by mistake
        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['lat' => 90.36, 'lng' => 23.80])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    public function test_lat_without_lng_is_rejected(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['lat' => 23.8])
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_inactive_area_cannot_be_chosen(): void
    {
        $kitchen = Kitchen::factory()->create();
        $closed = Area::factory()->create(['is_active' => false]);

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['area_id' => $closed->id])
            ->assertJsonValidationErrors(['area_id']);
    }

    public function test_user_without_kitchen_gets_404(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson('/api/v1/kitchen', ['display_name' => 'abc'])
            ->assertNotFound();
    }

    public function test_status_cannot_be_changed_by_the_cook(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['status' => 'approved', 'display_name' => 'নতুন নাম'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.display_name', 'নতুন নাম');
    }
}
