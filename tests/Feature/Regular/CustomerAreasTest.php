<?php

namespace Tests\Feature\Regular;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F111: adding a customer offers only the areas where delivery runs now; admins tick more. */
class CustomerAreasTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_ticked_areas_are_offered_and_an_admin_can_tick_more(): void
    {
        Area::query()->update(['for_customers' => false]);
        $badda = Area::where('slug', 'madhya-badda')->first() ?? Area::factory()->create(['slug' => 'madhya-badda']);
        $mirpur = Area::where('slug', 'mirpur-10')->first() ?? Area::factory()->create(['slug' => 'mirpur-10']);

        $this->assertArrayHasKey($mirpur->id, Area::customerOptions()); // none ticked: all areas
        $badda->update(['for_customers' => true]);
        $this->assertSame([$badda->id], array_keys(Area::customerOptions()));
        $this->assertArrayHasKey($mirpur->id, Area::customerOptions($mirpur->id)); // her own area stays

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageAreas::class)->call('updateTableColumnState', 'for_customers', (string) $mirpur->id, true);
        $this->assertTrue($mirpur->fresh()->for_customers);
        $this->assertArrayHasKey($mirpur->id, Area::customerOptions());
    }
}
