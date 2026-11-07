<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\MeResource;
use App\Services\Otp\OtpService;
use App\Services\Otp\OtpThrottledException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /** POST /api/v1/auth/logout — revoke the current token. */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
