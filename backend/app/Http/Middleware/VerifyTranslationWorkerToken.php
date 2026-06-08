<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTranslationWorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) config('services.flat_translation.worker_token', ''));

        if ($configuredToken === '') {
            return response()->json([
                'message' => 'Translation worker token is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $providedToken = trim((string) $request->header('X-Translation-Worker-Token', ''));

        if (! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Valid translation worker token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
