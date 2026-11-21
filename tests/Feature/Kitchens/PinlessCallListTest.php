<?php

namespace Tests\Feature\Kitchens;

use App\Models\Area;
use App\Models\Kitchen;
use App\Models\PinLink;
use App\Models\Remark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F93: Badda's cooks without a pin, as a call list with each cook's own link. */
class PinlessCallListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_get_badda_cooks_without_a_pin_each_with_her_own_link(): void
    {
        $badda = Area::where('name', 'মধ্য বাড্ডা')->first() ?? Area::factory()->create(['name' => 'মধ্য বাড্ডা']);
        $other = Area::where('name', 'মিরপুর ১০')->first() ?? Area::factory()->create(['name' => 'মিরপুর ১০']);
        $admin = User::factory()->admin()->create();
        $a = Kitchen::factory()->approved()->create(['area_id' => $badda->id, 'location' => null, 'daily_plates' => 2]);
        $b = Kitchen::factory()->create(['area_id' => $badda->id, 'location' => null]);
        Kitchen::factory()->approved()->create(['area_id' => $badda->id]); // has a pin
        Kitchen::factory()->approved()->create(['area_id' => $other->id, 'location' => null]); // not Badda
        Remark::create(['user_id' => $a->user_id, 'body' => 'বিকেলে ফোন দিতে বলেছেন', 'created_by' => $admin->id]);
        $old = PinLink::forKitchen($a);

        $csv = $this->actingAs($admin)->get('/admin-tools/pinless-calls.csv?view=1')->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(3, $lines); // header + 2 cooks
        $this->assertStringContainsString('https://wa.me/88'.$a->user->phone, $csv);
        $this->assertStringContainsString('2 প্লেট', $csv);
        $this->assertStringContainsString('বিকেলে ফোন দিতে বলেছেন', $csv);
        $this->assertStringContainsString($old->url(), $csv); // her valid link is reused
        $this->assertStringContainsString(PinLink::where('kitchen_id', $b->id)->firstOrFail()->url(), $csv);

        $this->actingAs(User::factory()->create())->get('/admin-tools/pinless-calls.csv')->assertForbidden();
    }
}
