<?php

namespace Tests\Feature\ShareCards;

use App\Enums\PostStatus;
use App\Events\PostPublished;
use App\Jobs\GenerateShareCard;
use App\Models\Post;
use App\Services\ShareCards\BrowsershotRenderer;
use App\Services\ShareCards\ShareCardData;
use App\Services\ShareCards\ShareCardRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShareCardTest extends TestCase
{
    use RefreshDatabase;

    /** Captures the HTML instead of starting Chrome. */
    private array $rendered = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
        $this->app->instance(ShareCardRenderer::class, new class($this->rendered) implements ShareCardRenderer
        {
            public function __construct(private array &$rendered) {}

            public function render(string $html, int $width, int $height): string
            {
                $this->rendered[] = compact('html', 'width', 'height');

                return "\xFF\xD8\xFF fake jpeg";
            }
        });
    }

    public function test_publishing_queues_a_card_only_when_enabled(): void
    {
        Queue::fake();
        $post = Post::factory()->create();

        config(['dawat.share_cards.enabled' => false]);
        PostPublished::dispatch($post);
        Queue::assertNothingPushed();

        config(['dawat.share_cards.enabled' => true]);
        PostPublished::dispatch($post);
        Queue::assertPushed(GenerateShareCard::class, fn ($job) => $job->post->is($post));
    }

    public function test_the_job_renders_the_post_and_stores_the_image(): void
    {
        $post = Post::factory()->create(['title' => 'রুই মাছের কালিয়া', 'price_paisa' => 18000, 'note' => 'একটু ঝাল']);

        (new GenerateShareCard($post))->handle(app(ShareCardRenderer::class));

        $html = $this->rendered[0]['html'];
        $this->assertSame([1080, 1350], [$this->rendered[0]['width'], $this->rendered[0]['height']]);
        $this->assertStringContainsString('রুই মাছের কালিয়া', $html);
        $this->assertStringContainsString('৳১৮০', $html);
        $this->assertStringContainsString('একটু ঝাল', $html);
        $this->assertStringContainsString('<svg', $html, 'QR code to the post');
        $this->assertStringContainsString('data:font/woff2;base64,', $html, 'fonts are inlined');

        $path = $post->fresh()->share_image_path;
        $this->assertMatchesRegularExpression('#^posts/\d+/share-[a-z0-9]{8}\.jpg$#', $path);
        Storage::disk(config('filesystems.media_disk'))->assertExists($path);
    }

    public function test_the_card_never_contains_location_or_phone(): void
    {
        $post = Post::factory()->create();
        $post->kitchen->update(['contact_phone' => '01812345678', 'contact_name' => 'করিম']);
        $home = $post->kitchen->fresh()->location;

        (new GenerateShareCard($post))->handle(app(ShareCardRenderer::class));
        $html = $this->rendered[0]['html'];

        $this->assertStringNotContainsString('01812345678', $html);
        $this->assertStringNotContainsString($post->kitchen->user->phone, $html);
        $this->assertStringNotContainsString(substr((string) $home->lat, 0, 7), $html);
    }

    public function test_regenerating_replaces_the_old_file(): void
    {
        $post = Post::factory()->create();
        $job = new GenerateShareCard($post);

        $job->handle(app(ShareCardRenderer::class));
        $first = $post->fresh()->share_image_path;
        $job->handle(app(ShareCardRenderer::class));
        $second = $post->fresh()->share_image_path;

        $this->assertNotSame($first, $second);
        Storage::disk(config('filesystems.media_disk'))->assertMissing($first);
    }

    public function test_feed_and_post_page_expose_the_card_url(): void
    {
        $post = Post::factory()->create();
        (new GenerateShareCard($post))->handle(app(ShareCardRenderer::class));

        $url = $this->getJson("/api/v1/posts/{$post->id}")->json('data.share_image_url');
        $this->assertStringEndsWith($post->fresh()->share_image_path, $url);
    }

    public function test_shared_link_has_open_graph_tags_with_the_card(): void
    {
        $post = Post::factory()->create(['title' => 'খিচুড়ি', 'price_paisa' => 12000]);
        (new GenerateShareCard($post))->handle(app(ShareCardRenderer::class));
        $this->withoutVite();

        $this->get("/p/{$post->id}")
            ->assertOk()
            ->assertSee('property="og:title" content="খিচুড়ি · ৳১২০', false)
            ->assertSee($post->fresh()->share_image_path, false)
            ->assertSee('id="app"', false);
    }

    public function test_hidden_or_unknown_posts_get_generic_tags(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Hidden, 'title' => 'গোপন খাবার']);
        $this->withoutVite();

        $this->get("/p/{$post->id}")->assertOk()->assertDontSee('গোপন খাবার');
        $this->get('/p/999999')->assertOk()->assertSee('পাড়ার ঘরের খাবার');
        $this->get('/p/abc')->assertOk();
    }

    /**
     * Real headless Chrome. Runs where SHARE_CARD_NODE_MODULES points at a
     * Puppeteer install (developer machine, server); skipped in CI.
     */
    public function test_real_chrome_renders_a_jpeg(): void
    {
        $modules = env('SHARE_CARD_NODE_MODULES');
        if (! $modules || ! is_dir($modules.'/puppeteer') || ! is_executable((string) config('dawat.share_cards.chrome_path'))) {
            $this->markTestSkipped('Puppeteer/Chrome not set up on this machine.');
        }

        $post = Post::factory()->create(['title' => 'ভাত, মুরগির ঝোল, ডাল']);
        $html = view('share.card', ShareCardData::for($post))->render();

        $jpeg = (new BrowsershotRenderer)->render($html, 1080, 1350);

        $this->assertStringStartsWith("\xFF\xD8\xFF", $jpeg);
        [$w, $h] = getimagesizefromstring($jpeg);
        $this->assertSame([1080, 1350], [$w, $h]);
    }
}
