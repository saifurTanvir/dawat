<?php

namespace Tests\Feature;

use Tests\TestCase;

/** F81: the privacy policy Meta links to. */
class PrivacyPageTest extends TestCase
{
    public function test_the_privacy_policy_is_public_and_says_how_to_delete_data(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('গোপনীয়তা নীতি')->assertSee('id="delete"', false)->assertSee('delete my data');
    }
}
