<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\MeResource;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Otp\LoggingOtpSender;
use App\Services\Otp\LoginHelp;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpThrottledException;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class OtpController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    /** POST /api/v1/auth/otp — send a code by SMS. */
    public function request(RequestOtpRequest $request): JsonResponse
    {
        try {
            $this->otp->request($request->validated('phone'), $request->ip());
        } catch (OtpThrottledException $e) {
            return response()->json([
                'message' => "একটু পরে আবার চেষ্টা করুন ({$e->retryAfterSeconds} সেকেন্ড)।",
                'retry_after' => $e->retryAfterSeconds,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return response()->json([
            'message' => 'আপনার মোবাইলে একটি কোড পাঠানো হয়েছে।',
            'resend_after' => OtpService::RESEND_AFTER,
            // Lets the app show a hint on a developer's machine; always false elsewhere.
            'dev_any_code' => OtpService::anyCodeAccepted(),
        ], Response::HTTP_ACCEPTED);
    }

    /** POST /api/v1/auth/verify — exchange the code for an API token. */
    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->otp->verify($request->validated('phone'), $request->validated('code'));

        if (! $user) {
            return response()->json([
                'message' => 'কোডটি সঠিক নয় বা মেয়াদ শেষ। নতুন কোড চেয়ে আবার চেষ্টা করুন।',
                'errors' => ['code' => ['কোডটি সঠিক নয় বা মেয়াদ শেষ।']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->isBlocked()) {
            return response()->json([
                'message' => 'এই নম্বরটি বন্ধ করা আছে। সাহায্যের জন্য দাওয়াতের সাথে যোগাযোগ করুন।',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken($request->validated('device_name') ?: 'pwa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => MeResource::make($user),
        ]);
    }

    /** POST /api/v1/auth/link — F50: a one-time login link the admin sent. */
    public function link(Request $request, LoginHelp $help): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);
        $user = $help->useLink($data['token']);

        if (! $user) {
            return response()->json(['message' => 'লিংকটা আর কাজ করছে না (ব্যবহার হয়ে গেছে বা ৩০ মিনিট পেরিয়েছে)। দাওয়াতের কাছে নতুন লিংক চান।'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($user->isBlocked()) {
            return response()->json(['message' => 'এই নম্বরটি বন্ধ করা আছে। সাহায্যের জন্য দাওয়াতের সাথে যোগাযোগ করুন।'], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'token' => $user->createToken('pwa-link')->plainTextToken,
            'user' => MeResource::make($user),
        ]);
    }

    /** POST /api/v1/auth/no-code — F50: "কোড আসেনি": tell the admins, and where to message Dawat. */
    public function noCode(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);
        $phone = PhoneNumber::normalize($data['phone']);
        abort_unless($phone, 422, 'নম্বরটা ঠিক নেই।');

        if (Cache::add('no-code:'.$phone, 1, now()->addMinutes(10))) {
            SmsLog::create(['phone' => $phone, 'user_id' => User::where('phone', $phone)->value('id'), 'purpose' => 'login', 'status' => 'reported']);
            LoggingOtpSender::alertAdmins("কোড পাননি: {$phone}", 'লগইন কোড পৌঁছায়নি বলে জানিয়েছেন। অ্যাডমিন থেকে হোয়াটসঅ্যাপে কোড বা লগইন লিংক দিন।', $phone);
        }

        return response()->json([
            'message' => 'দাওয়াত টিমকে জানানো হয়েছে। হোয়াটসঅ্যাপে মেসেজ দিলে দ্রুত সাহায্য পাবেন।',
            'whatsapp_url' => LoginHelp::whatsappUrl((string) config('dawat.contact.phone'), "আসসালামু আলাইকুম। আমার নম্বর {$phone}, দাওয়াতে ঢোকার কোড পাইনি।"),
        ]);
    }

    /** POST /api/v1/auth/logout — revoke the current token. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
