<?php

namespace Tests\Feature\Pwa;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F36: the link people send on WhatsApp to install the app. */
class InstallPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_install_page_is_public_with_its_own_title(): void
    {
        $this->withoutVite();

        $this->get('/app')
            ->assertOk()
            ->assertSee('<title>দাওয়াত অ্যাপ ইনস্টল করুন — পাড়ার ঘরের খাবার</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/app').'">', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('id="app"', false);
    }

    public function test_the_install_qr_points_at_the_install_page(): void
    {
        $this->get('/app/qr.svg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }
}
