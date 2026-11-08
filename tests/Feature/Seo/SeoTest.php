<?php

namespace Tests\Feature\Seo;

use App\Enums\PostStatus;
use App\Models\Area;
use App\Models\Kitchen;
use App\Models\Post;
use App\Support\Geo\GeoPoint;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F31: what Google reads. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migration_sets_the_badda_side_service_areas(): void
    {
        $active = Area::active()->orderBy('sort_order')->pluck('slug')->all();

        $this->assertSame([
            'uttar-badda', 'madhya-badda', 'dakkhin-badda', 'merul-badda', 'shahjadpur', 'satarkul',
            'bashundhara', 'rampura', 'gulshan-1', 'gulshan-2', 'banani', 'mohakhali',
        ], $active);
    }

    public function test_home_page_has_readable_text_meta_and_structured_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>দাওয়াত — ঢাকায় পাড়ার ঘরের খাবার, দুপুরের লাঞ্চ | Dawat</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('"alternateName":["Dawat"', false)
            ->assertSee('+8801685284290', false)
            ->assertSee('<h1', false)
            ->assertSee('href="'.url('/area/uttar-badda').'"', false)
            ->assertSee('id="app"', false);
    }

    public function test_area_page_lists_todays_dishes_without_any_location(): void
    {
        $area = Area::where('slug', 'uttar-badda')->first();
        $kitchen = Kitchen::factory()->approved()->at(23.7861, 90.4251)->create(['area_id' => $area->id, 'display_name' => 'রহিমা খালার রান্নাঘর']);
        Post::factory()->for($kitchen)->create(['title' => 'ভাত, মুরগির ঝোল, ডাল', 'price_paisa' => 15000]);
        Post::factory()->for($kitchen)->create(['title' => 'গতকালের খিচুড়ি', 'available_until' => now()->subHour()]);
        Post::factory()->for($kitchen)->create(['title' => 'লুকানো পোস্ট', 'status' => PostStatus::Hidden]);

        $html = $this->get('/area/uttar-badda')
            ->assertOk()
            ->assertSee('<h1 class="mt-3 font-serif text-3xl font-bold">উত্তর বাড্ডায় ঘরের খাবার</h1>', false)
            ->assertSee('ভাত, মুরগির ঝোল, ডাল')
            ->assertSee('৳১৫০')
            ->assertSee('রহিমা খালার রান্নাঘর')
            ->assertDontSee('গতকালের খিচুড়ি')
            ->assertDontSee('লুকানো পোস্ট')
            ->assertSee('/?area=uttar-badda', false)
            ->assertSee('"@type":"Service"', false)
            ->getContent();

        $this->assertStringNotContainsString('23.786', $html);
        $this->assertStringNotContainsString('90.42', $html);
        $this->assertStringNotContainsString('"lat"', $html);
    }

    public function test_inactive_or_unknown_areas_are_not_found(): void
    {
        Area::factory()->create(['slug' => 'old-area', 'is_active' => false]);

        $this->get('/area/old-area')->assertNotFound();
        $this->get('/area/nowhere')->assertNotFound();
    }

    public function test_service_pages_render_with_the_contact_number(): void
    {
        foreach (['/lunch' => 'প্রতিদিন দুপুরে ঘরের রান্না', '/rannaghor' => 'বাসার রান্না থেকে আয় করুন', '/about' => 'প্রশ্ন ও উত্তর'] as $path => $text) {
            $this->get($path)
                ->assertOk()
                ->assertSee($text)
                ->assertSee('০১৬৮৫২৮৪২৯০')
                ->assertSee('<link rel="canonical" href="'.url($path).'">', false);
        }

        $this->get('/about')->assertSee('"@type":"FAQPage"', false);
    }

    public function test_sitemap_lists_public_pages_areas_and_live_posts_only(): void
    {
        $live = Post::factory()->create();
        $expired = Post::factory()->create(['available_until' => now()->subHour()]);
        Area::factory()->create(['slug' => 'old-area', 'is_active' => false]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.url('/lunch').'</loc>', false)
            ->assertSee('<loc>'.url('/area/bashundhara').'</loc>', false)
            ->assertSee('<loc>'.url('/p/'.$live->id).'</loc>', false)
            ->assertDontSee('/p/'.$expired->id.'<', false)
            ->assertDontSee('old-area')
            ->assertDontSee('/admin');
    }

    public function test_robots_keeps_private_screens_out_and_points_at_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /orders')
            ->assertSee('Sitemap: '.url('/sitemap.xml'));

        $this->artisan('dawat:robots')->assertSuccessful();
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), file_get_contents(public_path('robots.txt')));
        @unlink(public_path('robots.txt'));
    }

    public function test_private_screens_are_noindex_and_unknown_paths_are_404(): void
    {
        $this->get('/login')->assertOk()->assertSee('content="noindex,nofollow"', false);
        $this->get('/orders')->assertOk()->assertSee('content="noindex,nofollow"', false);
        $this->get('/q/uttar-badda')->assertOk()->assertSee('content="noindex,nofollow"', false);

        $this->get('/no-such-page')->assertNotFound()->assertSee('id="app"', false)->assertSee('content="noindex,follow"', false);
    }

    public function test_post_pages_leave_the_index_when_the_meal_is_over(): void
    {
        $live = Post::factory()->create();
        $over = Post::factory()->create(['available_until' => now()->subHour()]);

        $this->get('/p/'.$live->id)->assertOk()
            ->assertSee('content="index,follow"', false)
            ->assertSee('<link rel="canonical" href="'.url('/p/'.$live->id).'">', false);
        $this->get('/p/'.$over->id)->assertOk()->assertSee('content="noindex,follow"', false);
        $this->get('/p/999999')->assertNotFound();
    }

    public function test_bangla_place_suffixes(): void
    {
        $this->assertSame('উত্তর বাড্ডায়', Seo::in('উত্তর বাড্ডা'));
        $this->assertSame('বনানীতে', Seo::in('বনানী'));
        $this->assertSame('শাহজাদপুরে', Seo::in('শাহজাদপুর'));
        $this->assertSame('গুলশান ১-এ', Seo::in('গুলশান ১'));
        $this->assertSame('রামপুরা ও বসুন্ধরার', Seo::of('রামপুরা ও বসুন্ধরা'));
        $this->assertSame('সাতারকুলের', Seo::of('সাতারকুল'));
    }

    public function test_home_page_does_not_leak_area_centres(): void
    {
        $banani = Area::where('slug', 'banani')->first();
        $banani->center = GeoPoint::make(23.79371, 90.40451);
        $banani->save();

        $this->assertStringNotContainsString('23.7937', $this->get('/')->getContent());
    }
}
