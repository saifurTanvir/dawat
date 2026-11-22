<?php

namespace Tests\Feature\Ops;

use App\Console\Commands\OpsSmsOutsideCooks;
use App\Models\Area;
use App\Models\Kitchen;
use App\Services\Geo\FocusSplit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/** F101: thank-you SMS to cooks outside Badda, once each. */
class SmsOutsideCooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_cooks_outside_badda_get_it_and_only_once(): void
    {
        Sleep::fake();
        config(['services.bulksmsbd' => ['base_url' => 'https://sms.test', 'api_key' => 'k', 'sender_id' => 's']]);
        Http::fake(['sms.test/api/getBalanceApi*' => Http::response(['balance' => 900]), 'sms.test/*' => Http::response(['response_code' => 202])]);

        $badda = Area::whereIn('id', app(FocusSplit::class)->focusAreaIds())->firstOrFail();
        $other = Area::factory()->create(['slug' => 'test-mirpur']);
        Kitchen::factory()->approved()->create(['area_id' => $badda->id]);
        $out = Kitchen::factory()->approved()->create(['area_id' => $other->id]);

        $this->artisan('ops:sms-outside-cooks')->expectsOutputToContain('2 SMS parts each')->expectsOutputToContain('To send: 1 numbers')->assertSuccessful();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/smsapi'));

        $this->artisan('ops:sms-outside-cooks --send')->expectsOutputToContain('Sent 1, failed 0')->assertSuccessful();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/smsapi') && $r['number'] === '88'.$out->user->phone && $r['message'] === OpsSmsOutsideCooks::TEXT);

        $this->artisan('ops:sms-outside-cooks --send')->expectsOutputToContain('Sent 0, failed 0')->assertSuccessful();
    }
}
