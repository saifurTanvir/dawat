<?php

namespace Tests\Feature\Admin;

use App\Enums\ContactType;
use App\Filament\Widgets\DailyActivityChart;
use App\Filament\Widgets\DawatStats;
use App\Filament\Widgets\TopKitchens;
use App\Filament\Widgets\TopQrCodes;
use App\Models\ContactEvent;
use App\Models\Post;
use App\Models\QrCode;
use App\Models\User;
use App\Support\DhakaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_dashboard_renders_with_all_widgets(): void
    {
        Post::factory()->create();

        $this->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(DawatStats::class)
            ->assertSeeLivewire(DailyActivityChart::class)
            ->assertSeeLivewire(TopKitchens::class)
            ->assertSeeLivewire(TopQrCodes::class);
    }

    public function test_stats_count_today_in_dhaka_time(): void
    {
        // 01:00 on 8 Nov in Dhaka is 19:00 on 7 Nov UTC.
        $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 7)->setTime(19, 0));
        $post = Post::factory()->create();
        ContactEvent::create(['post_id' => $post->id, 'type' => ContactType::Message]);
        // 23:30 Dhaka on 7 Nov = yesterday for a Dhaka admin.
        ContactEvent::forceCreate(['post_id' => $post->id, 'type' => ContactType::Call, 'created_at' => now()->subMinutes(90)]);

        $this->assertTrue(DhakaTime::startOfDay()->equalTo(now()->subHour()));

        Livewire::test(DawatStats::class)
            ->assertSee('আজকের যোগাযোগ')
            ->assertSee('মেসেজ 1 · কল 0');
    }

    public function test_chart_groups_by_dhaka_day_and_rejects_odd_filters(): void
    {
        $post = Post::factory()->create();
        ContactEvent::create(['post_id' => $post->id, 'type' => ContactType::Share]);

        $data = $this->chartData(Livewire::test(DailyActivityChart::class)->set('filter', '7')->instance());
        $this->assertCount(7, $data['labels']);
        $this->assertSame(1, end($data['datasets'][0]['data']));

        // A crafted filter value falls back to 14 days instead of reaching the query.
        $data = $this->chartData(Livewire::test(DailyActivityChart::class)->set('filter', '1; DROP TABLE posts')->instance());
        $this->assertCount(14, $data['labels']);
    }

    /** getData() is protected in Filament; read it the way the widget does. */
    private function chartData(DailyActivityChart $chart): array
    {
        return (fn () => $this->getData())->call($chart);
    }

    public function test_top_kitchens_ranks_by_contacts_this_week(): void
    {
        $busy = Post::factory()->create();
        $quiet = Post::factory()->create();
        foreach (range(1, 3) as $i) {
            ContactEvent::create(['post_id' => $busy->id, 'type' => ContactType::Message]);
        }

        Livewire::test(TopKitchens::class)
            ->assertCanSeeTableRecords([$busy->kitchen, $quiet->kitchen], inOrder: true);
    }

    public function test_top_qr_codes_lists_scans(): void
    {
        $qr = QrCode::factory()->create(['scans' => 12]);

        Livewire::test(TopQrCodes::class)->assertCanSeeTableRecords([$qr]);
    }
}
