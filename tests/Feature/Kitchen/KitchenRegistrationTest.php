<?php

namespace Tests\Feature\Kitchen;

use App\Enums\KitchenStatus;
use App\Models\Kitchen;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_register_a_pending_kitchen(): void
    {
        $user = User::factory()->role(Roles::BUYER)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/kitchen', ['display_name' => 'রহিমা খালার রান্নাঘর'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.can_post', false)
            ->assertJsonMissingPath('data.location');

        $this->assertTrue($user->fresh()->isCook());
        $this->assertSame(KitchenStatus::Pending, $user->kitchen->status);
    }

    public function test_a_user_cannot_register_twice(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->postJson('/api/v1/kitchen', ['display_name' => 'আরেকটা'])
            ->assertStatus(409);
    }

    public function test_guests_cannot_register(): void
    {
        $this->postJson('/api/v1/kitchen', ['display_name' => 'x x'])->assertUnauthorized();
    }

    public function test_me_shows_kitchen_status(): void
    {
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($kitchen->user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertJsonPath('data.kitchen.status', 'pending')
            ->assertJsonPath('data.roles', [Roles::COOK]);
    }

    public function test_show_returns_404_without_kitchen(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/kitchen')
            ->assertNotFound();
    }

    public function test_only_approved_and_set_up_kitchens_may_publish(): void
    {
        $pending = Kitchen::factory()->create();
        $approved = Kitchen::factory()->approved()->create();
        $approvedNoLocation = Kitchen::factory()->approved()->notSetUp()->create();
        $suspended = Kitchen::factory()->create(['status' => KitchenStatus::Suspended]);

        $this->assertFalse($pending->user->can('publish-posts'));
        $this->assertTrue($approved->user->can('publish-posts'));
        $this->assertFalse($approvedNoLocation->user->can('publish-posts'));
        $this->assertFalse($suspended->user->can('publish-posts'));
    }

    public function test_approval_records_who_and_when(): void
    {
        $kitchen = Kitchen::factory()->create();
        $admin = User::factory()->admin()->create();

        $kitchen->approve($admin);

        $this->assertSame(KitchenStatus::Approved, $kitchen->fresh()->status);
        $this->assertSame($admin->id, $kitchen->fresh()->approved_by);
        $this->assertNotNull($kitchen->fresh()->approved_at);

        $kitchen->reject($admin, 'ছবি পরিষ্কার নয়');
        $this->assertSame('ছবি পরিষ্কার নয়', $kitchen->fresh()->rejection_reason);
    }
}
