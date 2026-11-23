<?php

namespace Tests\Feature\Admin;

use App\Enums\CallRequestStatus;
use App\Filament\Resources\CallRequests\Pages\ManageCallRequests;
use App\Filament\Widgets\CallList;
use App\Filament\Widgets\CallOnboardingPending;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\Customer;
use App\Models\Kitchen;
use App\Models\User;
use App\Services\Admin\CallOnboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** F70: a finished call puts the person in the system. */
class CallOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
        $this->area = Area::first() ?? Area::factory()->create();
    }

    private function req(string $reason, string $phone = '01742891024', string $name = 'সুফিয়া'): CallRequest
    {
        return CallRequest::create(['phone' => $phone, 'name' => $name, 'reason' => $reason]);
    }

    public function test_it_guesses_role_and_area_from_the_reason(): void
    {
        $r = $this->req($this->area->name.'। ৪ ঘণ্টা আগে জানালে ৮–১০ জনের রান্না করতে চান');
        $this->assertSame('cook', CallOnboarding::guessRole($r));
        $this->assertSame($this->area->id, CallOnboarding::guessArea($r));
        $this->assertSame('customer', CallOnboarding::guessRole($this->req('অফিসে দুপুরে নিয়মিত খাবার নিতে চান', '01711111111')));
        $this->assertSame('shahidar', CallOnboarding::guessRole($this->req('বাইক আছে, এলাকা ম্যানেজার হতে চান', '01722222222')));
        $this->assertSame('account', CallOnboarding::guessRole($this->req('কিছু প্রশ্ন আছে', '01733333333')));
    }

    public function test_closing_a_call_adds_the_person_as_a_cook(): void
    {
        $r = $this->req('রান্না করতে চান');

        Livewire::test(CallList::class)->callTableAction('note', $r, [
            'body' => 'কথা হলো, কাল থেকে শুরু', 'status' => 'pending', 'close' => true,
            'onboard_role' => 'cook', 'onboard_name' => 'সুফিয়া আক্তার', 'area_id' => $this->area->id,
        ])->assertHasNoTableActionErrors();

        $r->refresh();
        $this->assertSame(CallRequestStatus::Done, $r->status);
        $this->assertSame('cook', $r->onboard_result);
        $kitchen = Kitchen::whereHas('user', fn ($q) => $q->where('phone', '01742891024'))->firstOrFail();
        $this->assertSame('সুফিয়া আক্তার-এর রান্নাঘর', $kitchen->display_name);
        $this->assertSame('pending', $kitchen->status->value);
        $this->assertSame($kitchen->user_id, $r->user_id);
    }

    public function test_closing_a_call_adds_a_customer_and_someone_already_in_is_left_alone(): void
    {
        $r = $this->req('নিয়মিত খাবার নিতে চান', '01711111111', 'রহিম');
        Livewire::test(ManageCallRequests::class)->removeTableFilter('in_badda')->callTableAction('note', $r, [
            'body' => 'দুপুরে নেবেন', 'status' => 'pending', 'close' => true,
            'onboard_role' => 'customer', 'onboard_name' => 'রহিম', 'area_id' => $this->area->id, 'address' => 'বাসা ৪, রোড ২',
        ])->assertHasNoTableActionErrors();
        $this->assertSame('বাসা ৪, রোড ২', Customer::whereHas('user', fn ($q) => $q->where('phone', '01711111111'))->value('address'));

        $cook = Kitchen::factory()->create();
        $known = $this->req('আরেকটা প্রশ্ন', $cook->user->phone);
        $this->assertNotNull(CallOnboarding::existing($known));
        Livewire::test(CallList::class)->callTableAction('note', $known, ['body' => 'উত্তর দিলাম', 'status' => 'pending', 'close' => true])
            ->assertHasNoTableActionErrors();
        $this->assertSame('existing', $known->fresh()->onboard_result);
    }

    public function test_calls_closed_before_show_until_added(): void
    {
        $old = $this->req('রান্না করতে চান');
        $old->forceFill(['status' => CallRequestStatus::Done, 'done_at' => now()])->save();
        $skipped = $this->req('শুধু জানতে চেয়েছিলেন', '01755555555');
        $skipped->forceFill(['status' => CallRequestStatus::Done])->save();

        $this->assertTrue(CallOnboardingPending::canView());
        Livewire::test(CallOnboardingPending::class)->assertCanSeeTableRecords([$old, $skipped])
            ->callTableAction('onboard', $old, ['onboard_role' => 'cook', 'onboard_name' => 'সুফিয়া', 'area_id' => $this->area->id])
            ->assertHasNoTableActionErrors()
            ->callTableAction('onboard', $skipped, ['onboard_role' => 'skip'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, Kitchen::count());
        $this->assertSame('skip', $skipped->fresh()->onboard_result);
        $this->assertFalse(CallOnboardingPending::canView());
    }

    public function test_a_shahidar_or_just_an_account(): void
    {
        $r = $this->req('এলাকা ম্যানেজার হতে চান', '01722222222', 'করিম');
        $r->forceFill(['status' => CallRequestStatus::Done])->save();
        Livewire::test(CallOnboardingPending::class)
            ->callTableAction('onboard', $r, ['onboard_role' => 'shahidar', 'onboard_name' => 'করিম', 'area_ids' => [$this->area->id]])
            ->assertHasNoTableActionErrors();
        $this->assertTrue(User::where('phone', '01722222222')->firstOrFail()->isShahidar());

        $q = $this->req('প্রশ্ন ছিল', '01733333333', 'জলি');
        $q->forceFill(['status' => CallRequestStatus::Done])->save();
        Livewire::test(CallOnboardingPending::class)->callTableAction('onboard', $q, ['onboard_role' => 'account', 'onboard_name' => 'জলি']);
        $this->assertSame('account', $q->fresh()->onboard_result);
        $this->assertSame('জলি', User::where('phone', '01733333333')->value('name'));
    }
}
