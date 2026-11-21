<?php

namespace Tests\Feature\Messenger;

use App\Models\BotFact;
use App\Services\Messenger\Agent;
use App\Services\Messenger\Brain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner, 21 Nov: nobody is called আপু/ভাই — everyone is "আপনি". */
class NoApuBhaiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bot_calls_nobody_apu_or_bhai(): void
    {
        $sentences = implode("\n", Agent::WELCOME);
        $this->assertDoesNotMatchRegularExpression('/\bapu\b|\bbhai\b|আপু/u', $sentences);

        $prompt = str_replace(['"apu", "apa", "bhai", "bhaiya"'], '', Brain::SYSTEM);
        $this->assertDoesNotMatchRegularExpression('/\bapu\b|\bbhai\b/u', $prompt);
        $this->assertStringContainsString('never call anyone "apu"', Brain::SYSTEM);

        $this->assertStringContainsString('আপনি', BotFact::promptText());
        $this->assertStringNotContainsString('ডেলিভারি ভাই', BotFact::promptText());
    }
}
