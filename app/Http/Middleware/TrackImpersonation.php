<?php

namespace App\Http\Middleware;

use App\Models\ImpersonationAction;
use App\Services\Admin\Impersonate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F51: while someone uses the app as another person, every change is
 * logged; their phone, contact number and notifications are off limits.
 */
class TrackImpersonation
{
    /** Paths that stay with the real person. */
    private const BLOCKED = ['api/v1/push/subscribe', 'api/v1/kitchen/contact'];

    public function handle(Request $request, Closure $next): Response
    {
        $imp = Impersonate::current($request->user());
        if (! $imp) {
            return $next($request);
        }
        if (! $imp->isLive()) {
            return response()->json(['message' => 'অন্যের অ্যাপ দেখার সময় শেষ। নিজের অ্যাকাউন্টে ফিরে যান।', 'impersonation_ended' => true], 401);
        }
        foreach (self::BLOCKED as $path) {
            if (str_starts_with($request->path(), $path) && ! $request->isMethod('GET')) {
                return response()->json(['message' => 'অন্যের অ্যাপে এটা করা যায় না — এটা তাঁকে নিজেই করতে হবে।'], 403);
            }
        }

        $response = $next($request);

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            ImpersonationAction::create([
                'impersonation_id' => $imp->id,
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 200),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
