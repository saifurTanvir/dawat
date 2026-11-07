<?php

namespace Tests\Feature\Admin;

use App\Enums\KitchenStatus;
use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Kitchens\Pages\ListKitchens;
use App\Filament\Resources\Kitchens\Pages\ViewKitchen;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Kitchen;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_only_admins_can_open_the_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->cook()->create())->get('/admin')->assertForbidden();

        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    public function test_every_list_page_renders(): void
    {
        Post::factory()->create();
        Report::create(['reportable_type' => Post::class, 'reportable_id' => Post::first()->id, 'reason' => 'spam']);

        $this->actingAs($this->admin);
        foreach (['kitchens', 'posts', 'reports', 'users'] as $page) {
            $this->get("/admin/{$page}")->assertOk();
        }
        $this->get('/admin/kitchens/'.Kitchen::first()->id)->assertOk();
        $this->get('/admin/posts/'.Post::first()->id)->assertOk();
    }

    public function test_admin_approves_a_pending_kitchen(): void
    {
        $kitchen = Kitchen::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test(ListKitchens::class)
            ->assertCanSeeTableRecords([$kitchen])
            ->callAction(TestAction::make('approve')->table($kitchen))
            ->assertNotified();

        $kitchen->refresh();
        $this->assertSame(KitchenStatus::Approved, $kitchen->status);
        $this->assertSame($this->admin->id, $kitchen->approved_by);
    }

    public function test_reject_needs_a_reason(): void
    {
        $kitchen = Kitchen::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])
            ->callAction('reject', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        Livewire::test(ViewKitchen::class, ['record' => $kitchen->id])
            ->callAction('reject', ['reason' => 'ফোনে পাওয়া যায়নি']);

        $this->assertSame(KitchenStatus::Rejected, $kitchen->fresh()->status);
        $this->assertSame('ফোনে পাওয়া যায়নি', $kitchen->fresh()->rejection_reason);
    }

    public function test_admin_hides_and_restores_a_post(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test(ListPosts::class)->callAction(TestAction::make('hide')->table($post));
        $this->assertSame(PostStatus::Hidden, $post->fresh()->status);

        Livewire::test(ListPosts::class)->callAction(TestAction::make('unhide')->table($post));
        $this->assertSame(PostStatus::Live, $post->fresh()->status);
    }

    public function test_blocking_a_user_revokes_their_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('pwa');
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)->callAction(TestAction::make('block')->table($user));

        $this->assertSame(UserStatus::Blocked, $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_cannot_block_themselves(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ListUsers::class)->assertActionHidden(TestAction::make('block')->table($this->admin));
    }

    public function test_resolving_a_report(): void
    {
        $report = Report::create(['reportable_type' => Post::class, 'reportable_id' => Post::factory()->create()->id, 'reason' => 'spam']);
        $this->actingAs($this->admin);

        Livewire::test(ListReports::class)->callAction(TestAction::make('resolve')->table($report));

        $this->assertSame(ReportStatus::Resolved, $report->fresh()->status);
        $this->assertSame($this->admin->id, $report->fresh()->resolved_by);
    }
}
