<?php

namespace Tests\Feature\Ads;

use App\Models\MessengerThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdFunnelCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_each_chat_with_numbers_masked(): void
    {
        $t = MessengerThread::create(['psid' => 'F1', 'last_in_at' => now()]);
        $t->messages()->create(['direction' => 'in', 'by' => 'person', 'text' => 'দাম কত?']);
        $t->messages()->create(['direction' => 'out', 'by' => 'bot', 'text' => 'nam, number ar thikana likhun']);
        $t->messages()->create(['direction' => 'in', 'by' => 'person', 'text' => 'amar number 01712345678']);

        $this->artisan('ops:ad-funnel customer')
            ->expectsOutputToContain('customer: 1 chats')
            ->expectsOutputToContain('#####')
            ->doesntExpectOutputToContain('01712345678')
            ->assertSuccessful();
    }
}
