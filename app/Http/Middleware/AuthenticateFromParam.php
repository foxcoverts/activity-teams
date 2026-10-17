<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFromParam
{
    public function handle(Request $request, Closure $next, string $param, string $key = 'id'): Response
    {
        $model = $request->route($param);

        if ($model && Auth::onceUsingId($model->{$key})) {
            return $next($request);
        }

        throw new AuthenticationException('Unauthenticated.');
    }
}
