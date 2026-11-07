<?php

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_message_and_comment_without_calls(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->getJson('/api/v1/kitchen')
            ->assertJsonPath('data.allow_call', false)
            ->assertJsonPath('data.allow_message', true)
            ->assertJsonPath('data.allow_comment', true);
    }

    public function test_cook_turns_on_calls_and_uses_a_shahidar_number(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', [
                'allow_call' => true,
                'contact_phone' => '+880 1812-345678',
                'contact_name' => 'করিম ভাই',
            ])
            ->assertOk()
            ->assertJsonPath('data.allow_call', true)
            ->assertJsonPath('data.contact_phone', '01812345678');

        $this->assertSame('01812345678', $kitchen->fresh()->publicContactPhone());
    }

    public function test_without_a_contact_number_buyers_reach_the_cook(): void
    {
        $kitchen = Kitchen::factory()->create(['contact_phone' => '01812345678', 'contact_name' => 'করিম']);

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['contact_phone' => null, 'contact_name' => null])
            ->assertOk();

        $this->assertSame($kitchen->user->phone, $kitchen->fresh()->publicContactPhone());
    }

    public function test_a_shahidar_number_needs_a_name(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['contact_phone' => '01812345678'])
            ->assertJsonValidationErrors(['contact_name']);
    }

    public function test_invalid_contact_number_is_rejected(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['contact_phone' => '12345', 'contact_name' => 'x'])
            ->assertJsonValidationErrors(['contact_phone']);
    }

    public function test_all_contact_ways_cannot_be_switched_off(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['allow_call' => false, 'allow_message' => false, 'allow_comment' => false])
            ->assertJsonValidationErrors(['allow_comment']);

        // Turning off one while another stays on is fine.
        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['allow_comment' => false])
            ->assertOk();
    }
}
