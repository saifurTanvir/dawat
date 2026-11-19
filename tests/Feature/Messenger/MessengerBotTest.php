<?php

namespace Tests\Feature\Messenger;

use App\Enums\KitchenStatus;
use App\Filament\Resources\MessengerThreads\Pages\ManageMessengerThreads;
use App\Jobs\AnswerMessenger;
use App\Models\Area;
use App\Models\CallRequest;
use App\Models\Kitchen;
use App\Models\MessengerThread;
use App\Models\OpsHeartbeat;
use App\Models\User;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Graph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** F80: Messenger answered by the server through Meta's webhook. */
class MessengerBotTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.messenger.app_secret' => 'test-secret',
            'services.messenger.page_token' => 'page-token',
            'services.messenger.graph' => 'https://graph.test',
            'services.ai.anthropic_key' => 'ai-key',
        ]);
        Graph::setBot(true);
        User::factory()->admin()->create(['name' => 'মালিক']);
        $this->area = Area::where('slug', 'jatrabari')->first() ?? Area::factory()->create(['name' => 'যাত্রাবাড়ী', 'slug' => 'jatrabari']);
    }

    private function hook(array $messaging): TestResponse
    {
        $body = json_encode(['object' => 'page', 'entry' => [['id' => 'PAGE', 'messaging' => $messaging]]]);

        return $this->call('POST', '/api/webhooks/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        ], $body);
    }

    private function text(string $psid, string $text, string $mid): array
    {
        return ['sender' => ['id' => $psid], 'recipient' => ['id' => 'PAGE'], 'timestamp' => 1, 'message' => ['mid' => $mid, 'text' => $text]];
    }

    /** Fakes Meta and Claude; Claude answers with these facts. */
    private function fake(array $answer): void
    {
        Http::swap(new HttpFactory);
        Http::fake([
            'graph.test/*' => Http::response(['first_name' => 'Rina', 'last_name' => 'Akter', 'message_id' => 'm']),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'answer', 'input' => $answer + [
                'replies' => ['dhonnobad apu 😊'], 'name' => null, 'phone' => null, 'area_id' => null, 'area_text' => null, 'cook' => false,
                'plates' => null, 'send_pin_link' => false, 'wants_call' => false, 'call_reason' => null, 'needs_human' => false, 'human_reason' => null,
            ]]]]),
        ]);
    }

    /** @return list<string> texts sent to Messenger */
    private function sent(): array
    {
        return collect(Http::recorded())->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), '/me/messages') && isset($r['message']['text']))
            ->map(fn (Request $r) => $r['message']['text'])->values()->all();
    }

    private function answer(string $psid): MessengerThread
    {
        $t = MessengerThread::where('psid', $psid)->firstOrFail();
        app(Agent::class)->answer($t);

        return $t->fresh();
    }

    public function test_meta_can_verify_the_url_only_with_our_token(): void
    {
        $token = Graph::verifyToken();
        $this->get('/api/webhooks/messenger?hub.mode=subscribe&hub.verify_token='.$token.'&hub.challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get('/api/webhooks/messenger?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();
    }

    public function test_unsigned_posts_are_refused_and_signed_messages_are_kept_once_and_answered(): void
    {
        Queue::fake();
        $this->fake([]);
        $body = json_encode(['entry' => [['messaging' => [$this->text('P1', 'hi', 'mid.1')]]]]);
        $this->call('POST', '/api/webhooks/messenger', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad'], $body)->assertForbidden();
        $this->assertSame(0, MessengerThread::count());

        $this->hook([$this->text('P1', 'ami join korte chai', 'mid.1')])->assertOk();
        $this->hook([$this->text('P1', 'ami join korte chai', 'mid.1')])->assertOk(); // Meta retried
        $t = MessengerThread::where('psid', 'P1')->firstOrFail();
        $this->assertSame('Rina Akter', $t->name);
        $this->assertSame(1, $t->messages()->count());
        Queue::assertPushed(AnswerMessenger::class, 1);

        Graph::setBot(false);
        $this->hook([$this->text('P1', 'ki holo', 'mid.2')])->assertOk();
        Queue::assertPushed(AnswerMessenger::class, 1); // kept, not answered
        $this->assertSame(2, $t->messages()->count());
    }

    public function test_name_area_and_number_add_and_approve_her_and_she_alone_gets_her_pin_link(): void
    {
        Queue::fake();
        $this->hook([$this->text('P2', 'Rina, jatrabari, 01711-223344', 'mid.a')]);
        $this->fake(['name' => 'Rina', 'phone' => '01711-223344', 'area_id' => $this->area->id, 'area_text' => 'Jatrabari', 'cook' => true]);

        $t = $this->answer('P2');

        $kitchen = Kitchen::firstOrFail();
        $this->assertSame($kitchen->id, $t->kitchen_id);
        $this->assertSame(KitchenStatus::Approved, $kitchen->status);
        $this->assertSame('01711223344', $kitchen->user->phone);
        $this->assertSame($this->area->id, $kitchen->area_id);
        $texts = $this->sent();
        $this->assertStringContainsString('Jatrabari e add kore approve kore dilam', $texts[0]);
        $this->assertTrue(collect($texts)->contains(fn ($x) => str_contains($x, '/pin/')));
        $this->assertTrue(collect($texts)->contains(fn ($x) => str_contains($x, '1 plate dite parben na 2')));
        $this->assertSame(count($texts), $t->messages()->where('by', 'bot')->count());
        $this->assertSame('messenger', OpsHeartbeat::latest('id')->first()->channel);

        // Her answer about plates is saved.
        $this->hook([$this->text('P2', '2 plate', 'mid.b')]);
        $this->fake(['plates' => 2]);
        $this->answer('P2');
        $this->assertSame(2, $kitchen->fresh()->daily_plates);
        $this->assertSame(1, Kitchen::count());
    }

    public function test_a_number_that_already_has_a_kitchen_is_left_for_an_admin(): void
    {
        $old = Kitchen::factory()->approved()->create(['area_id' => $this->area->id]);
        $this->hook([$this->text('P3', 'hi', 'mid.c')]);
        $this->fake(['name' => 'Sima', 'phone' => $old->user->phone, 'area_id' => $this->area->id, 'cook' => true]);

        $t = $this->answer('P3');

        $this->assertSame(1, Kitchen::count());
        $this->assertNull($t->kitchen_id);
        $this->assertTrue($t->needs_human);
        $this->assertFalse(collect($this->sent())->contains(fn ($x) => str_contains($x, '/pin/')));
    }

    public function test_unknown_area_is_flagged_and_a_call_request_goes_on_the_call_list(): void
    {
        $this->hook([$this->text('P4', 'Mina, Bhola, 01811223344, amake call din', 'mid.d')]);
        $this->fake(['name' => 'Mina', 'phone' => '01811223344', 'area_text' => 'Bhola', 'cook' => true, 'wants_call' => true, 'call_reason' => 'যোগ দেওয়া নিয়ে কথা']);

        $t = $this->answer('P4');

        $this->assertSame(0, Kitchen::count());
        $this->assertTrue($t->needs_human);
        $this->assertStringContainsString('Bhola', $t->human_reason);
        $call = CallRequest::firstOrFail();
        $this->assertSame('messenger', $call->source);
        $this->assertSame('01811223344', $call->phone);
    }

    public function test_a_person_answering_from_the_page_pauses_the_bot_but_our_own_echoes_do_not(): void
    {
        Queue::fake();
        $this->fake([]);
        $this->hook([$this->text('P5', 'hi', 'mid.e')]);
        $t = MessengerThread::where('psid', 'P5')->firstOrFail();
        $t->forceFill(['last_in_at' => now()->subMinutes(5)])->save();

        $echo = fn (string $mid, array $extra = []) => ['sender' => ['id' => 'PAGE'], 'recipient' => ['id' => 'P5'], 'message' => ['mid' => $mid, 'is_echo' => true, 'text' => 'ami nijei bolchi'] + $extra];
        $this->hook([$echo('mid.ours', ['metadata' => 'dawat-bot'])]);
        $this->assertTrue($t->fresh()->botMayAnswer());

        $this->hook([$echo('mid.owner')]);
        $this->assertFalse($t->fresh()->botMayAnswer());
        $this->assertSame(0, app(Agent::class)->answer($t->fresh()));
    }

    public function test_without_ai_she_still_gets_the_greeting_and_an_admin_is_asked(): void
    {
        config(['services.ai.anthropic_key' => null]);
        Http::fake(['graph.test/*' => Http::response(['message_id' => 'm']), 'api.anthropic.com/*' => Http::response([], 401)]);
        $this->hook([$this->text('P6', 'hello', 'mid.f')]);

        $t = $this->answer('P6');

        $this->assertSame(Agent::WELCOME, $this->sent());
        $this->assertTrue($t->needs_human);
    }

    public function test_replies_never_carry_outside_or_whatsapp_links(): void
    {
        $this->assertSame('dekhun', Agent::clean('dekhun wa.me/8801711'));
        $this->assertSame('ekhane: https://dawat.cloud/x', Agent::clean('ekhane: https://dawat.cloud/x https://evil.example/y'));
    }

    public function test_admins_read_and_answer_a_chat_from_the_panel_and_switch_the_bot(): void
    {
        Queue::fake();
        $this->fake([]);
        $this->hook([$this->text('P7', 'apnara ki taka den?', 'mid.g')]);
        $t = MessengerThread::where('psid', 'P7')->firstOrFail();
        $this->actingAs(User::role('admin')->first(), 'web');

        $this->get('/admin/messenger')->assertOk()->assertSee('Rina Akter');
        Livewire::test(ManageMessengerThreads::class)
            ->callTableAction('chat', $t, ['reply' => 'ji apu, shahidar cash e diye jan'])->assertHasNoTableActionErrors()
            ->callAction('toggle');
        $this->assertSame(['ji apu, shahidar cash e diye jan'], $this->sent());
        $this->assertFalse($t->fresh()->botMayAnswer()); // paused after a person answered
        $this->assertFalse(Graph::botOn());
    }

    public function test_the_status_page_hears_from_the_bot_and_a_newer_message_takes_over_the_answer(): void
    {
        Queue::fake();
        $this->fake([]);
        $this->hook([$this->text('P8', 'hi', 'mid.h1')]);
        $this->hook([$this->text('P8', 'ami rannar kaj korte chai', 'mid.h2')]);
        $t = MessengerThread::where('psid', 'P8')->firstOrFail();
        $first = $t->messages()->min('id');

        (new AnswerMessenger($t->id, $first))->handle(app(Agent::class));
        $this->assertSame([], $this->sent()); // the newer message's job answers both

        $t->forceFill(['last_in_at' => now()->subMinutes(5)])->save();
        $this->artisan('messenger:heartbeat')->assertSuccessful();
        $beat = OpsHeartbeat::latest('id')->firstOrFail();
        $this->assertSame(1, $beat->pending);
        $this->assertTrue($beat->problem);
    }

    public function test_metas_instant_greeting_does_not_pause_the_bot_and_admins_can_answer_the_backlog(): void
    {
        Queue::fake();
        $this->fake([]);
        // The greeting can arrive before their first message reaches us.
        $this->hook([['sender' => ['id' => 'PAGE'], 'recipient' => ['id' => 'P9'], 'message' => ['mid' => 'mid.greet', 'is_echo' => true, 'text' => 'আসসালামু আলাইকুম! দাওয়াতে স্বাগতম']]]);
        $this->assertTrue(MessengerThread::where('psid', 'P9')->firstOrFail()->botMayAnswer());

        $this->hook([$this->text('P9', 'kivabe join korbo', 'mid.i')]);
        MessengerThread::where('psid', 'P9')->update(['last_in_at' => now()->subMinutes(10)]);
        Queue::fake();
        $this->actingAs(User::role('admin')->first(), 'web');
        Livewire::test(ManageMessengerThreads::class)->callAction('answerWaiting')
            ->callTableAction('botAnswer', MessengerThread::where('psid', 'P9')->first());
        Queue::assertPushed(AnswerMessenger::class, 2);
    }
}
