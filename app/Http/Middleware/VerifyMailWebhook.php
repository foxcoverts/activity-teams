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

        $provided = $request->bearerToken()
            ?? $request->header('X-Webhook-Secret')
            ?? $request->header('X-Forwarded-Email-Secret');

        if (! is_string($provided) || ! hash_equals($secret, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
