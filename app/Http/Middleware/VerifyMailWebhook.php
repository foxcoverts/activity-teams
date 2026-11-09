<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMailWebhook
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('mail.webhook_secret');

        if ($secret === '') {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $signature = $request->header('X-Webhook-Signature');

        if (is_string($signature) && $signature !== '') {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);

            if (hash_equals($expected, $signature)) {
                return $next($request);
            }

            abort(Response::HTTP_UNAUTHORIZED);
        }

        // Fallback for local/manual callers (tests may also use Bearer).
        $provided = $request->bearerToken()
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-Forwarded-Email-Secret');

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
