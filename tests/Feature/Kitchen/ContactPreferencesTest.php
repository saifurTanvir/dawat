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

    public function test_contact_number_cannot_be_set_directly(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->patchJson('/api/v1/kitchen', ['contact_phone' => '01812345678', 'contact_name' => 'x'])
            ->assertJsonValidationErrors(['contact_phone', 'contact_name']);
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
