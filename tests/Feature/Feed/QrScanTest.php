<?php

namespace Tests\Feature\Feed;

use App\Models\Area;
use App\Models\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_returns_the_area_and_counts_once_per_phone(): void
    {
        $qr = QrCode::factory()->create(['label' => 'বায়তুল আমান মসজিদ']);

        $this->getJson("/api/v1/qr/{$qr->code}")
            ->assertOk()
            ->assertJsonPath('data.area.slug', $qr->area->slug)
            ->assertJsonPath('data.label', 'বায়তুল আমান মসজিদ');

        // Reloading the page does not inflate the count…
        $this->getJson("/api/v1/qr/{$qr->code}")->assertOk();
        $this->assertSame(1, $qr->fresh()->scans);
        $this->assertNotNull($qr->fresh()->last_scanned_at);

        // …another phone does.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->getJson("/api/v1/qr/{$qr->code}");
        $this->assertSame(2, $qr->fresh()->scans);
    }

    public function test_codes_are_case_insensitive(): void
    {
        $qr = QrCode::factory()->create();

        $this->getJson('/api/v1/qr/'.strtoupper($qr->code))->assertOk();
    }

    public function test_inactive_or_unknown_codes_are_404(): void
    {
        $off = QrCode::factory()->create(['is_active' => false]);
        $closedArea = QrCode::factory()->for(Area::factory()->state(['is_active' => false]))->create();

        $this->getJson("/api/v1/qr/{$off->code}")->assertNotFound();
        $this->getJson("/api/v1/qr/{$closedArea->code}")->assertNotFound();
        $this->getJson('/api/v1/qr/nothere1')->assertNotFound();
        $this->assertSame(0, $off->fresh()->scans);
    }
}
