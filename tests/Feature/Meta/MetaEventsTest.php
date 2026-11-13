<?php

namespace Tests\Feature\Meta;

use App\Jobs\SendMetaEvent;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** F54: Facebook pixel + Conversions API. */
class MetaEventsTest extends TestCase
{
    use RefreshDatabase;

    private function turnOn(bool $server = true): void
    {
        config([
            'services.meta.pixel_id' => '1234567890',
            'services.meta.capi_token' => $server ? 'test-token' : null,
        ]);
    }

    public function test_nothing_is_tracked_when_the_pixel_is_off(): void
    {
        Bus::fake();
        $user = User::factory()->role(Roles::BUYER)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/kitchen', ['display_name' => 'রহিমা খালার রান্নাঘর'])
            ->assertCreated()
            ->assertJsonPath('meta_event', null);

        Bus::assertNotDispatched(SendMetaEvent::class);
        $this->get('/')->assertDontSee('fbevents.js');
    }

    public function test_the_pixel_is_on_the_page_when_set(): void
    {
        $this->turnOn();

        $this->get('/')->assertSee('fbevents.js')->assertSee('1234567890');
    }

    public function test_a_new_kitchen_is_reported_by_server_and_browser_with_one_id(): void
    {
        Bus::fake();
        $this->turnOn();
        $user = User::factory()->role(Roles::BUYER)->create(['phone' => '01711000000']);

        $response = $this->actingAs($user, 'sanctum')
            ->withCookie('_fbp', 'fb.1.123.456')
            ->postJson('/api/v1/kitchen', ['display_name' => 'রহিমা খালার রান্নাঘর'])
            ->assertCreated()
            ->assertJsonPath('meta_event.name', 'CompleteRegistration');

        $id = $response->json('meta_event.id');
        $this->assertSame('cook-'.$user->fresh()->kitchen->id, $id);

        Bus::assertDispatched(SendMetaEvent::class, function (SendMetaEvent $job) use ($id) {
            $data = $job->event['user_data'];

            return $job->event['event_id'] === $id
                && $job->event['event_name'] === 'CompleteRegistration'
                && $data['ph'] === [hash('sha256', '8801711000000')]
                && ! str_contains(json_encode($job->event), '01711000000');
        });
    }

    public function test_only_the_browser_reports_when_there_is_no_server_token(): void
    {
        Bus::fake();
        $this->turnOn(server: false);
        $user = User::factory()->role(Roles::BUYER)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/kitchen', ['display_name' => 'রহিমা খালার রান্নাঘর'])
            ->assertJsonPath('meta_event.name', 'CompleteRegistration');

        Bus::assertNotDispatched(SendMetaEvent::class);
    }

    public function test_the_job_posts_to_the_conversions_api(): void
    {
        $this->turnOn();
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);

        (new SendMetaEvent(['event_name' => 'Lead', 'event_id' => 'x-1']))->handle();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/1234567890/events')
            && $request['data'][0]['event_id'] === 'x-1');
    }
}
