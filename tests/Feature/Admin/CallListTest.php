<?php

namespace Tests\Feature\Admin;

use App\Enums\CallRequestStatus;
use App\Filament\Resources\CallRequests\Pages\ManageCallRequests;
use App\Filament\Widgets\CallList;
use App\Models\CallRequest;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F66: "যোগাযোগ করতে হবে" — people the owner has to call himself. */
class CallListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'সাইফুর']);
        $this->actingAs($this->admin);
    }

    public function test_add_from_the_list_links_the_account_and_shows_on_the_dashboard(): void
    {
        $kitchen = Kitchen::factory()->create();
        $phone = $kitchen->user->phone;

        $this->assertFalse(CallList::canView());

        Livewire::test(ManageCallRequests::class)
            ->callAction('create', ['phone' => '+88'.$phone, 'name' => 'রেহানা', 'reason' => 'খাবার দেওয়ার আগে কিছু প্রশ্ন আছে', 'source' => 'whatsapp'])
            ->assertHasNoActionErrors();

        $r = CallRequest::sole();
        $this->assertSame($phone, $r->phone);
        $this->assertSame($kitchen->user_id, $r->user_id);
        $this->assertSame($this->admin->id, $r->created_by);
        $this->assertSame(CallRequestStatus::Pending, $r->status);
        $this->assertSame('tel:+88'.$phone, $r->telUrl());
        $this->assertSame('https://wa.me/88'.$phone, $r->whatsappUrl());

        $this->assertTrue(CallList::canView());
        Livewire::test(CallList::class)->assertCanSeeTableRecords([$r])->assertSee('কিছু প্রশ্ন আছে');
    }

    public function test_notes_status_and_done(): void
    {
        $r = CallRequest::create(['phone' => '01713259733', 'reason' => 'আগে কথা বলতে চান']);
        $this->assertNull($r->user_id);

        Livewire::test(ManageCallRequests::class)
            ->callTableAction('note', $r, ['body' => 'ফোন ধরেননি', 'status' => 'no_answer'])
            ->assertHasNoTableActionErrors();
        $r->refresh();
        $this->assertSame(CallRequestStatus::NoAnswer, $r->status);
        $this->assertSame('ফোন ধরেননি', $r->latestNote());
        $this->assertTrue(CallList::canView());

        Livewire::test(ManageCallRequests::class)
            ->callTableAction('note', $r, ['body' => 'কথা হয়েছে, কাল থেকে দেবেন', 'status' => 'pending'])
            ->callTableAction('done', $r);
        $r->refresh();
        $this->assertSame(CallRequestStatus::Done, $r->status);
        $this->assertSame($this->admin->id, $r->done_by);
        $this->assertCount(2, $r->notes);
        $this->assertSame('কথা হয়েছে, কাল থেকে দেবেন', $r->latestNote());
        $this->assertFalse(CallList::canView());

        Livewire::test(ManageCallRequests::class)->assertCanSeeTableRecords([$r])->assertSee('কথা হয়েছে');
    }

    public function test_bad_phone_is_refused_and_non_admins_cannot_open_the_page(): void
    {
        Livewire::test(ManageCallRequests::class)
            ->callAction('create', ['phone' => '12345', 'reason' => 'x', 'source' => 'whatsapp'])
            ->assertHasActionErrors(['phone']);

        $this->actingAs(User::factory()->create());
        $this->get('/admin/call-list')->assertForbidden();
    }

    public function test_a_long_reason_is_one_short_line_and_opens_whole(): void
    {
        $long = 'খাবার দেওয়ার আগে আপনার সাথে ফোনে কথা বলতে চান, কিছু প্রশ্ন আছে। WhatsApp এ দুবার কল করেছিলেন, কাল সকালে আবার ফোন দিতে বলেছেন।';
        $r = CallRequest::create(['phone' => '01713259733', 'name' => 'রেহানা', 'reason' => $long]);
        $r->addNote('ফোন ধরেননি', $this->admin);
        $r->save();

        Livewire::test(CallList::class)
            ->assertDontSee('কাল সকালে আবার ফোন দিতে বলেছেন')
            ->assertSee('রিমার্কস ১টা · পুরোটা দেখুন')
            ->assertTableActionVisible('reason', $r)
            ->callTableAction('reason', $r)
            ->assertHasNoTableActionErrors();

        Livewire::test(ManageCallRequests::class)->assertDontSee('কাল সকালে আবার ফোন দিতে বলেছেন')->assertTableActionVisible('reason', $r);
    }
}
