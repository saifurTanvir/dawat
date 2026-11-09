<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_api_answers_ping(): void
    {
        $this->getJson('/api/v1/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_spa_shell_is_served_for_app_paths(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('id="app"', false);
        $this->get('/q/uttar-badda')->assertOk()->assertSee('id="app"', false);
        // F31: Vue shows its "not found" screen, search engines get a real 404.
        $this->get('/updates')->assertNotFound()->assertSee('id="app"', false);
    }

    public function test_unknown_api_paths_return_json_404(): void
    {
        $this->getJson('/api/v1/does-not-exist')->assertNotFound();
    }

    public function test_trusted_proxies_come_from_config(): void
    {
        $this->assertSame([], config('dawat.trusted_proxies'));
    }
}
