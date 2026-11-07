<?php

namespace Tests\Feature\Ai;

use App\Models\Kitchen;
use App\Models\User;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Support\JpegWithExif;
use Tests\TestCase;

class FoodAssistantTest extends TestCase
{
    use RefreshDatabase;

    private Kitchen $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TagSeeder::class);
        $this->kitchen = Kitchen::factory()->approved()->create();
        config(['services.ai.driver' => 'anthropic', 'services.ai.anthropic_key' => 'test-key', 'services.ai.model' => 'claude-haiku-5-5']);
    }

    private function photo(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, JpegWithExif::make(800, 600, 1));

        return new UploadedFile($path, 'bhat.jpg', 'image/jpeg', null, true);
    }

    private function fakeTool(string $name, array $input): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'Here you go.'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => $name, 'input' => $input],
            ],
            'stop_reason' => 'tool_use',
        ])]);
    }

    private function asCook()
    {
        return $this->actingAs($this->kitchen->user, 'sanctum')->withHeaders(['Accept' => 'application/json']);
    }

    public function test_photo_suggestion_returns_title_and_only_allowed_tags(): void
    {
        $this->fakeTool('describe_food', [
            'title' => 'ভাত, মুরগির ঝোল',
            'tag_slugs' => ['spice-medium', 'halal', 'contains-nuts', 'made-up-tag', 'spice-medium'],
            'photo_ok' => true,
            'photo_problem' => null,
        ]);

        $this->asCook()->post('/api/v1/ai/photo', ['image' => $this->photo()])
            ->assertOk()
            ->assertExactJson(['data' => [
                'title' => 'ভাত, মুরগির ঝোল',
                'tag_slugs' => ['spice-medium'],
                'photo_ok' => true,
                'photo_problem' => null,
            ]]);

        Http::assertSent(function (Request $request) {
            $body = $request->data();
            $image = $body['messages'][0]['content'][0];
            $jpeg = base64_decode($image['source']['data']);

            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'test-key')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && $body['model'] === 'claude-haiku-5-5'
                && $body['tools'][0]['strict'] === true
                && $image['source']['media_type'] === 'image/jpeg'
                && str_starts_with($jpeg, "\xFF\xD8")
                // Re-encoded: the photo's GPS block never leaves the server.
                && ! str_contains($jpeg, 'GPS') && ! str_contains($jpeg, 'Exif');
        });
    }

    public function test_bad_photo_is_flagged(): void
    {
        $this->fakeTool('describe_food', [
            'title' => null, 'tag_slugs' => [], 'photo_ok' => false, 'photo_problem' => 'ছবিটা খুব অন্ধকার।',
        ]);

        $this->asCook()->post('/api/v1/ai/photo', ['image' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('data.photo_ok', false)
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.photo_problem', 'ছবিটা খুব অন্ধকার।');
    }

    public function test_note_is_polished(): void
    {
        $this->fakeTool('polished_note', ['note' => 'আজ লবণ একটু কম হয়েছে, ঝাল মাঝারি।']);

        $this->asCook()->postJson('/api/v1/ai/note', ['note' => 'লবন একটু কম হইছে ঝাল মাঝারি', 'title' => 'ডাল'])
            ->assertOk()
            ->assertJsonPath('data.note', 'আজ লবণ একটু কম হয়েছে, ঝাল মাঝারি।');

        Http::assertSent(fn (Request $r) => str_contains($r->data()['system'], 'keep every honest warning')
            && str_contains($r->data()['messages'][0]['content'][0]['text'], 'লবন একটু কম হইছে'));
    }

    public function test_upstream_failure_gives_a_bangla_503(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['type' => 'overloaded_error']], 529)]);

        $this->asCook()->postJson('/api/v1/ai/note', ['note' => 'লবণ কম'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'এখন সাজেশন দেওয়া যাচ্ছে না। নিজে লিখে পোস্ট করুন।');
    }

    public function test_an_answer_without_the_tool_is_treated_as_unavailable(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ভাত']]])]);

        $this->asCook()->post('/api/v1/ai/photo', ['image' => $this->photo()])->assertStatus(503);
    }

    public function test_switched_off_without_a_key(): void
    {
        config(['services.ai.driver' => 'none']);
        Http::fake();

        $this->asCook()->postJson('/api/v1/ai/note', ['note' => 'লবণ কম'])->assertStatus(503);
        $this->asCook()->getJson('/api/v1/kitchen')->assertJsonPath('data.ai_available', false);
        Http::assertNothingSent();

        config(['services.ai.driver' => 'anthropic']);
        $this->asCook()->getJson('/api/v1/kitchen')->assertJsonPath('data.ai_available', true);
    }

    public function test_only_approved_cooks_may_use_it(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson('/api/v1/ai/note', ['note' => 'লবণ কম'])->assertForbidden();
        $this->actingAs(Kitchen::factory()->create()->user, 'sanctum')
            ->postJson('/api/v1/ai/note', ['note' => 'লবণ কম'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/ai/note', ['note' => 'লবণ কম'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_non_images_are_refused_before_any_call(): void
    {
        Http::fake();

        $this->asCook()->post('/api/v1/ai/photo', ['image' => UploadedFile::fake()->createWithContent('x.jpg', 'nope')])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }
}
