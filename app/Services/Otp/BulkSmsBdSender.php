<?php

namespace App\Services\Otp;

use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkSMSBD (bulksmsbd.net). OTP_DRIVER=bulksmsbd
 * Needs BULKSMSBD_API_KEY and BULKSMSBD_SENDER_ID from the BulkSMSBD panel.
 * Success is response_code 202; everything else is logged by code (never the
 * phone number or the OTP) and reported to the user as "try again".
 */
class BulkSmsBdSender implements OtpSender
{
    /** Codes the panel documents; logged so the admin knows what to fix. */
    private const ERRORS = [
        1001 => 'invalid number',
        1002 => 'sender id not correct or disabled',
        1003 => 'required fields missing',
        1005 => 'internal error',
        1006 => 'balance validity not available',
        1007 => 'insufficient balance',
        1011 => 'user id not found',
        1012 => 'masking SMS must be sent in Bengali',
        1013 => 'sender id has no gateway',
        1014 => 'sender type name not found',
        1015 => 'sender id has no valid gateway',
        1016 => 'sender type active price info not found',
        1017 => 'sender type price info not found',
        1018 => 'account disabled',
        1019 => 'account sender type price disabled',
        1020 => 'parent account not found',
        1021 => 'parent account active price not found',
        1031 => 'account not verified',
        1032 => 'server IP not whitelisted in the BulkSMSBD panel',
    ];

    public function send(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::Login, array $context = []): void
    {
        $config = config('services.bulksmsbd');
        $local = PhoneNumber::normalize($phone);
        if (! $local || blank($config['api_key']) || blank($config['sender_id'])) {
            Log::error('BulkSMSBD: missing API key/sender id or bad number.');
            throw new OtpDeliveryFailedException('BulkSMSBD not configured.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($config['base_url'], '/').'/api/smsapi', [
                    'api_key' => $config['api_key'],
                    'senderid' => $config['sender_id'],
                    'type' => 'text',
                    'number' => '88'.$local,
                    'message' => $purpose->message($code, $context),
                ]);
        } catch (ConnectionException $e) {
            Log::error('BulkSMSBD unreachable: '.$e->getMessage());
            throw new OtpDeliveryFailedException('BulkSMSBD unreachable.', previous: $e);
        }

        $status = (int) $response->json('response_code');
        if ($response->failed() || $status !== 202) {
            Log::error('BulkSMSBD refused SMS', [
                'http' => $response->status(),
                'code' => $status,
                'reason' => self::ERRORS[$status] ?? $response->json('error_message'),
            ]);
            throw new OtpDeliveryFailedException("BulkSMSBD error {$status}.");
        }
    }
}
