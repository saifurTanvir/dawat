<?php

namespace Tests\Feature\Regular;

use App\Filament\Resources\Customers\CustomerResource;
use App\Jobs\SendCustomerWelcome;
use App\Models\Area;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** QA report: a customer the team adds in admin gets one welcome SMS. */
class CustomerWelcomeTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $extra = []): array
    {
        return [
            'phone' => '01712345678', 'name' => 'রাহেলা', 'area_id' => Area::factory()->create()->id, 'address' => 'বাসা ১২',
            'meal_slots' => ['lunch'], 'plates' => 1, 'weekdays' => [0, 1, 2, 3, 4, 5, 6], 'billing' => 'each', ...$extra,
        ];
    }

    public function test_a_new_customer_gets_a_welcome_sms_once(): void
    {
        Queue::fake();
        $customer = CustomerResource::createCustomer($this->data(['welcome_sms' => true]));
        Queue::assertPushed(SendCustomerWelcome::class, fn ($j) => $j->customerId === $customer->id);

        $sent = [];
        $this->mock(OtpSender::class)->shouldReceive('send')->once()
            ->withArgs(function ($phone, $code, $purpose, $context) use (&$sent) {
                $sent = [$phone, $purpose, OtpPurpose::CustomerWelcome->message('', $context)];

                return true;
            });
        (new SendCustomerWelcome($customer->id))->handle(app(OtpSender::class));
        $this->assertSame('01712345678', $sent[0]);
        $this->assertSame(OtpPurpose::CustomerWelcome, $sent[1]);
        $this->assertStringContainsString('স্বাগতম, রাহেলা', $sent[2]);

        // Entered again: no second welcome.
        Queue::fake();
        CustomerResource::createCustomer([...$this->data(['welcome_sms' => true]), 'area_id' => $customer->area_id]);
        Queue::assertNotPushed(SendCustomerWelcome::class);
    }

    public function test_the_team_can_skip_it(): void
    {
        Queue::fake();
        CustomerResource::createCustomer($this->data(['welcome_sms' => false]));
        Queue::assertNotPushed(SendCustomerWelcome::class);
    }
}
