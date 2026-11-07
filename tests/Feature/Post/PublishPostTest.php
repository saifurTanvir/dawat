<?php

namespace Tests\Feature\Post;

use App\Enums\MealSlot;
use App\Enums\PostStatus;
use App\Enums\TagType;
use App\Events\PostPublished;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JpegWithExif;
use Tests\TestCase;

class PublishPostTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.media_disk'));
        $this->seed(TagSeeder::class);
        $this->kitchen = Kitchen::factory()->approved()->at(23.8069, 90.3654)->create();
    }

    private function photo(string $name = 'bhat.jpg'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, JpegWithExif::make(800, 600, 1));

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'images' => [$this->photo(), $this->photo('dal.jpg')],
            'title' => 'ভাত, মুরগির ঝোল, ডাল',
            'price_taka' => 150,
            'plates_total' => 2,
            'meal_slot' => 'lunch',
            'available_hours' => 3,
            'tag_ids' => [Tag::where('slug', 'halal')->value('id'), Tag::where('slug', 'spice-medium')->value('id')],
            'note' => 'লবণ একটু কম হয়েছে।',
        ], $overrides);
    }

    public function test_an_approved_cook_publishes_a_post(): void
    {
        Event::fake([PostPublished::class]);
        $this->travelTo(now()->setTime(11, 30));

        $response = $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'ভাত, মুরগির ঝোল, ডাল')
            ->assertJsonPath('data.price_paisa', 15000)
            ->assertJsonPath('data.plates_left', 2)
            ->assertJsonPath('data.status', 'live')
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonMissingPath('data.location');

        $post = Post::findOrFail($response->json('data.id'));
        $this->assertSame(MealSlot::Lunch, $post->meal_slot);
        $this->assertTrue($post->available_until->equalTo(now()->addHours(3)));
        $this->assertSame($this->kitchen->area_id, $post->area_id);
        $this->assertEqualsWithDelta(23.8069, $post->location->lat, 1e-6);
        $this->assertSame(['halal', 'spice-medium'], $post->tags->pluck('slug')->sort()->values()->all());
        Storage::disk(config('filesystems.media_disk'))->assertExists($post->images->first()->path);
        Event::assertDispatched(PostPublished::class);
    }

    public function test_pending_or_unset_kitchens_cannot_post(): void
    {
        $pending = Kitchen::factory()->create();
        $notSetUp = Kitchen::factory()->approved()->notSetUp()->create();

        foreach ([$pending, $notSetUp] as $kitchen) {
            $this->actingAs($kitchen->user, 'sanctum')
                ->post('/api/v1/posts', $this->payload(), ['Accept' => 'application/json'])
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post('/api/v1/posts', $this->payload(), ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_photos_are_required_and_limited_to_four(): void
    {
        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['images' => []]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['images']);

        $five = array_map(fn () => $this->photo(), range(1, 5));
        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['images' => $five]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['images']);
    }

    public function test_a_fake_image_is_refused_with_a_bangla_message(): void
    {
        $fake = UploadedFile::fake()->createWithContent('x.jpg', 'not really an image');

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['images' => [$fake]]), ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame(0, Post::count());
    }

    public function test_only_one_spice_level_can_be_chosen(): void
    {
        $spice = Tag::where('type', TagType::Spice)->pluck('id')->take(2)->all();

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['tag_ids' => $spice]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_price_and_plate_limits(): void
    {
        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['price_taka' => 5, 'plates_total' => 50, 'available_hours' => 12]), ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['price_taka', 'plates_total', 'available_hours']);
    }

    public function test_a_failed_photo_rolls_back_everything(): void
    {
        $tiny = UploadedFile::fake()->image('tiny.jpg', 100, 100);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['images' => [$this->photo(), $tiny]]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images']);

        $this->assertSame(0, Post::count());
        $this->assertSame([], Storage::disk(config('filesystems.media_disk'))->allFiles());
    }

    public function test_daily_post_limit(): void
    {
        Post::factory()->count(8)->create(['kitchen_id' => $this->kitchen->id]);

        $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(429);
    }

    public function test_tags_endpoint_lists_active_tags_in_order(): void
    {
        Tag::where('slug', 'contains-shrimp')->update(['is_active' => false]);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('data.0.slug', 'halal');
    }

    public function test_status_cannot_be_injected(): void
    {
        $response = $this->actingAs($this->kitchen->user, 'sanctum')
            ->post('/api/v1/posts', $this->payload(['status' => 'hidden', 'kitchen_id' => 999]), ['Accept' => 'application/json'])
            ->assertCreated();

        $post = Post::find($response->json('data.id'));
        $this->assertSame(PostStatus::Live, $post->status);
        $this->assertSame($this->kitchen->id, $post->kitchen_id);
    }
}
