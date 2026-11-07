<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocked users lose access immediately, even with a valid token. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isBlocked()) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'এই নম্বরটি বন্ধ করা আছে। সাহায্যের জন্য দাওয়াতের সাথে যোগাযোগ করুন।',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
