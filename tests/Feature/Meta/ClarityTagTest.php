<?php

namespace Tests\Feature\Meta;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Session recordings run only when the owner has set a Clarity project id. */
class ClarityTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_without_an_id_and_on_with_one(): void
    {
        config(['services.clarity.project_id' => null]);
        $this->get('/')->assertOk()->assertDontSee('clarity.ms', false);

        config(['services.clarity.project_id' => 'abc123xyz']);
        $this->get('/')->assertOk()->assertSee('https://www.clarity.ms/tag/', false)->assertSee('"abc123xyz"', false);

        config(['services.clarity.project_id' => '"><script>x']);
        $this->get('/')->assertOk()->assertDontSee('clarity.ms', false);
    }
}
