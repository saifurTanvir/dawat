<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserStatus;
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
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    /** F57: new accounts without a code, per IP per day. */
    public const SIGNUPS_PER_IP_PER_DAY = 5;

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

    /**
     * POST /api/v1/auth/start — F57: the first screen of the app.
     *
     * A number with no account yet is signed up straight away, without waiting for
     * an SMS (many codes never arrive). The number stays "not verified" until an
     * admin confirms it on a call. A number that already has an account still needs
     * the SMS code: the number is the only key, so skipping it here would let anyone
     * open someone else's account.
     */
    public function start(RequestOtpRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');

        if (! User::where('phone', $phone)->exists()) {
            $key = 'signup:'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, self::SIGNUPS_PER_IP_PER_DAY)) {
                return response()->json(['message' => 'আজ এই সংযোগ থেকে অনেকগুলো নতুন অ্যাকাউন্ট খোলা হয়েছে। দাওয়াতের সাথে যোগাযোগ করুন।'], Response::HTTP_TOO_MANY_REQUESTS);
            }

            $user = DB::transaction(function () use ($phone) {
                $user = User::firstOrCreate(['phone' => $phone], ['status' => UserStatus::Active]);
                if (! $user->wasRecentlyCreated) {
                    return null; // someone signed up with this number a moment ago
                }
                $user->assignRole(Roles::BUYER);
                $user->forceFill(['verified_via' => LoginHelp::UNVERIFIED])->save();

                return $user;
            });

            if ($user) {
                RateLimiter::hit($key, 86400);
                LoggingOtpSender::alertAdmins("নতুন অ্যাকাউন্ট, নম্বর যাচাই হয়নি: {$phone}", 'কোড ছাড়াই অ্যাকাউন্ট খুলেছেন। কাজ দেওয়ার আগে ফোন করে নম্বর যাচাই করুন।', $phone);

                return response()->json([
                    'mode' => 'signed_up',
                    'message' => 'অ্যাকাউন্ট খোলা হয়েছে। নম্বরটা পরে দাওয়াত টিম ফোন করে যাচাই করবে।',
                    'token' => $user->createToken($request->input('device_name') ?: 'pwa')->plainTextToken,
                    'user' => MeResource::make($user),
                ], Response::HTTP_CREATED);
            }
        }

        // An account exists: sign in with the SMS code (F50 helps when it does not come).
        $response = $this->request($request);
        if ($response->getStatusCode() === Response::HTTP_ACCEPTED) {
            $response->setData([...$response->getData(true), 'mode' => 'code', 'message' => 'এই নম্বরে আগে অ্যাকাউন্ট খোলা আছে। ঢুকতে মোবাইলে পাঠানো কোডটি দিন।']);
        }

        return $response;
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
