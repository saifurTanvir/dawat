<?php

namespace Tests\Feature\Seo;

use App\Models\Area;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** QA re-check: "where Dawat runs" follows the areas ticked in admin, not a fixed "only Badda". */
class LaunchNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notice_names_the_ticked_areas(): void
    {
        Area::query()->update(['for_customers' => false]);
        $this->assertStringStartsWith('দাওয়াত এখন ঢাকার কয়েকটা এলাকায় চালু।', Area::launchNotice());

        Area::factory()->create(['name' => 'মধ্য বাড্ডা', 'for_customers' => true, 'sort_order' => 1]);
        Area::factory()->create(['name' => 'গুলশান', 'for_customers' => true, 'sort_order' => 2]);
        $notice = Area::launchNotice();
        $this->assertStringStartsWith('দাওয়াত এখন চালু: মধ্য বাড্ডা আর গুলশান।', $notice);
        $this->assertStringNotContainsString('শুধু বাড্ডায়', $notice);

        $this->get('/rannaghor')->assertOk()->assertSee('দাওয়াত এখন চালু: মধ্য বাড্ডা আর গুলশান')->assertDontSee('SMS-এ কোড আসে');
    }
}
