<?php

namespace Tests\Feature\Regular;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Kitchen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The pairing form: call or WhatsApp the suggested (or chosen) cook right there. */
class PairCallCookTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chosen_cook_can_be_called_or_messaged_from_the_pairing_form(): void
    {
        $kitchen = Kitchen::factory()->approved()->create(['display_name' => 'শারমিনের রান্নাঘর', 'contact_phone' => '01811111111']);
        $html = (string) CustomerResource::cookContact($kitchen->load('user'));

        $this->assertStringContainsString('tel:+88'.$kitchen->user->phone, $html);
        $this->assertStringContainsString('tel:+8801811111111', $html); // her other number too
        $this->assertStringContainsString('https://wa.me/88'.$kitchen->user->phone, $html);
        $this->assertStringContainsString('শারমিনের রান্নাঘর', $html);
        $this->assertNull(CustomerResource::cookContact(null));
    }
}
