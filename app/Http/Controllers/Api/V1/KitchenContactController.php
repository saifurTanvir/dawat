<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OwnKitchenResource;
use App\Models\Kitchen;
use App\Services\Otp\OtpPurpose;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpThrottledException;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F5: a cook may let buyers reach another number (a shahidar, a relative).
 * That number must agree first: either it receives an OTP and gives the code
 * to the cook, or a Dawat admin confirms it. Until then buyers reach the cook.
 */
class KitchenContactController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /** POST /api/v1/kitchen/contact {contact_name, contact_phone, method: otp|admin} */
    public function store(Request $request): JsonResponse
    {
        $kitchen = $this->kitchen($request);

        $request->merge(['contact_phone' => PhoneNumber::normalize($request->input('contact_phone')) ?? $request->input('contact_phone')]);
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'min:2', 'max:60'],
            'contact_phone' => ['required', 'string', 'regex:'.PhoneNumber::PATTERN, 'not_in:'.$request->user()->phone],
            'method' => ['required', 'in:otp,admin'],
        ], [
            'contact_name.required' => 'এই নম্বরটা কার, সেই নাম দিন (যেমন শাহিদারের নাম)।',
            'contact_phone.required' => 'মোবাইল নম্বর দিন।',
            'contact_phone.regex' => 'সঠিক মোবাইল নম্বর দিন, যেমন 017XXXXXXXX।',
            'contact_phone.not_in' => 'এটা আপনার নিজের নম্বর। নিজের নম্বরের জন্য কিছু করতে হবে না।',
            'method.required' => 'কীভাবে যাচাই করবেন বেছে নিন।',
        ]);

        if ($data['method'] === 'otp') {
            try {
                $this->otp->request($data['contact_phone'], $request->ip(), OtpPurpose::ContactNumber, ['kitchen' => $kitchen->display_name]);
            } catch (OtpThrottledException $e) {
                return response()->json([
                    'message' => "এই নম্বরে একটু আগেই কোড গেছে। {$e->retryAfterSeconds} সেকেন্ড পরে আবার চেষ্টা করুন।",
                    'retry_after' => $e->retryAfterSeconds,
                ], 429);
            }
        }

        $kitchen->requestContact($data['contact_phone'], trim($data['contact_name']), $data['method']);

        $message = $data['method'] === 'otp'
            ? "{$data['contact_phone']} নম্বরে একটা কোড গেছে। নম্বরের মালিকের কাছ থেকে কোডটা নিয়ে এখানে দিন।"
            : 'অনুরোধ পাঠানো হয়েছে। দাওয়াত টিম ওই নম্বরে কথা বলে অনুমোদন দেবে।';

        return OwnKitchenResource::make($kitchen->fresh()->load('area'))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode(202);
    }

    /** POST /api/v1/kitchen/contact/verify {code} — the code the other number received. */
    public function verify(Request $request): OwnKitchenResource|JsonResponse
    {
        $kitchen = $this->kitchen($request);

        if ($kitchen->pending_contact_method !== 'otp') {
            return response()->json(['message' => 'যাচাইয়ের জন্য কোনো কোড পাঠানো হয়নি।'], 409);
        }

        $code = preg_replace('/\D+/', '', strtr((string) $request->input('code'), ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']));

        if (! $this->otp->consume($kitchen->pending_contact_phone, $code)) {
            return response()->json([
                'message' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ। আবার কোড পাঠিয়ে চেষ্টা করুন।',
                'errors' => ['code' => ['কোডটি সঠিক নয় বা মেয়াদ শেষ।']],
            ], 422);
        }

        $kitchen->confirmPendingContact('otp');

        return OwnKitchenResource::make($kitchen->fresh()->load('area'))
            ->additional(['message' => 'নম্বর যাচাই হয়েছে। এখন থেকে ক্রেতারা এই নম্বরে যোগাযোগ করবেন।']);
    }

    /** DELETE /api/v1/kitchen/contact — back to the cook's own number, cancelling any request. */
    public function destroy(Request $request): OwnKitchenResource
    {
        $kitchen = $this->kitchen($request);
        $kitchen->removeContact();

        return OwnKitchenResource::make($kitchen->fresh()->load('area'));
    }

    private function kitchen(Request $request): Kitchen
    {
        return $request->user()->kitchen ?? abort(404, 'আপনি এখনো রাঁধুনি হিসেবে নিবন্ধন করেননি।');
    }
}
