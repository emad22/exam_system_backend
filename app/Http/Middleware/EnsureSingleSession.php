<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $isDemo = app(\App\Services\ExamService::class)->isDemoUser($user);
            if ($isDemo) {
                return $next($request);
            }
        }

        if ($user && $user->last_token_id !== null) {
            $currentTokenId = $user->currentAccessToken()->id;

            if ($currentTokenId !== $user->last_token_id) {
                $request->user()->currentAccessToken()->delete();

                return response()->json([
                    'message' => 'Session expired. Another login was detected.'
                ], 401);
            }
        }
        return $next($request);
    }
}
