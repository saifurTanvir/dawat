<?php

namespace Tests\Feature\Ads;

use App\Models\AdLead;
use App\Models\MessengerThread;
use App\Models\User;
use App\Services\Ads\BaddaAds;
use App\Services\Messenger\Graph;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BaddaAdsReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-21 18:00:00', 'Asia/Dhaka'));
        config(['services.messenger.app_secret' => 'test-secret', 'services.messenger.page_token' => 'page-token', 'services.messenger.graph' => 'https://graph.test']);
        Queue::fake();
    }

    private function hook(array $messaging): void
    {
        $body = json_encode(['object' => 'page', 'entry' => [['id' => 'PAGE', 'messaging' => $messaging]]]);
        $this->call('POST', '/api/webhooks/messenger', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
        ], $body)->assertOk();
    }

    public function test_ad_chats_whatsapp_and_comments_are_counted_per_ad(): void
    {
        Http::fake(['graph.test/*' => Http::response(['first_name' => 'Rina'])]);
        // A customer-ad chat known by its ad id (referral), answered by the bot.
        $this->hook([['sender' => ['id' => 'P1'], 'recipient' => ['id' => 'PAGE'], 'timestamp' => 1,
            'message' => ['mid' => 'm1', 'text' => 'khabar nite chai'], 'referral' => ['source' => 'ADS', 'ad_id' => BaddaAds::ADS['customer']['ad_id']]]]);
        $t1 = MessengerThread::where('psid', 'P1')->first();
        $this->assertSame(BaddaAds::ADS['customer']['ad_id'], $t1->facts['ad_id']);
        $t1->messages()->create(['direction' => 'out', 'by' => 'bot', 'text' => 'ji']);

        // An area-manager chat known only by the ad's greeting (Meta's message from the page); not answered yet.
        $t2 = MessengerThread::create(['psid' => 'P2', 'name' => 'Karim', 'last_in_at' => now()]);
        $t2->messages()->create(['direction' => 'out', 'by' => 'page', 'text' => 'আসসালামু আলাইকুম 🙏 এলাকা ম্যানেজার হতে আগ্রহের জন্য ধন্যবাদ!']);
        $t2->messages()->create(['direction' => 'in', 'by' => 'person', 'text' => 'ami korte chai']);

        // A cook greeting outside Badda is the other cook ad — not counted.
        $t3 = MessengerThread::create(['psid' => 'P3', 'last_in_at' => now(), 'facts' => ['area_text' => 'Mirpur 10']]);
        $t3->messages()->create(['direction' => 'out', 'by' => 'page', 'text' => 'যোগ দিতে চাইলে শুধু আপনার নাম, এলাকা']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/admin-tools/ad-leads', ['leads' => [
            ['ad' => 'cook', 'phone' => '+880 1712-345678', 'name' => 'Runa', 'replied' => true],
            ['ad' => 'cook', 'phone' => '01712345678', 'replied' => true],
        ]])->assertOk();
        AdLead::create(['ad' => 'customer', 'channel' => 'comment', 'key' => 'c1', 'name' => 'Sara', 'first_at' => now(), 'replied_at' => null]);

        $r = app(BaddaAds::class)->report();
        $this->assertSame([1, 1, 1, 0], [$r['customer']['messenger'], $r['customer']['messenger_answered'], $r['customer']['comments'], $r['customer']['comments_answered']]);
        $this->assertSame([1, 0], [$r['manager']['messenger'], $r['manager']['messenger_answered']]);
        $this->assertSame([0, 1, 1], [$r['cook']['messenger'], $r['cook']['whatsapp'], $r['cook']['whatsapp_answered']]);

        $this->actingAs($admin)->get('/admin/badda-ads')->assertOk()->assertSee('বাড্ডা অ্যাডের রিপোর্ট')->assertSee('Karim');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('বাড্ডা অ্যাড — আজকের হিসাব');
        $this->actingAs(User::factory()->cook()->create())->postJson('/admin-tools/ad-leads', ['leads' => []])->assertForbidden();
    }

    public function test_comments_on_the_ad_posts_are_read_with_the_page_token(): void
    {
        Http::fake([
            'graph.test/'.Graph::PAGE_ID.'/ads_posts*' => Http::response(['data' => [['id' => 'POST1', 'message' => 'নিজের এলাকায় সম্মানজনক কাজ খুঁজছেন?']]]),
            'graph.test/POST1/comments*' => Http::response(['data' => [
                ['id' => 'c9', 'from' => ['id' => 'U1', 'name' => 'Shirin'], 'message' => 'ami agrohi', 'created_time' => now()->toIso8601String(),
                    'comments' => ['data' => [['from' => ['id' => Graph::PAGE_ID]]]]],
            ]]),
        ]);
        $this->artisan('ads:sync-comments')->assertSuccessful();
        $lead = AdLead::where('key', 'c9')->first();
        $this->assertSame('manager', $lead->ad);
        $this->assertNotNull($lead->replied_at);
    }
}
