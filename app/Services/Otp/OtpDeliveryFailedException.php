<?php

namespace App\Services\Otp;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The SMS gateway did not accept the message. Shown to the user as a Bangla 503. */
class OtpDeliveryFailedException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'এখন কোড পাঠানো যাচ্ছে না। একটু পরে আবার চেষ্টা করুন।'], 503);
    }
}
