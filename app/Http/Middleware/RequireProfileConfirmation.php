<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireProfileConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return $next($request);
        }

        if ($request->routeIs('profile.confirm', 'account.deleted', 'logout') || $request->is('livewire/*')) {
            return $next($request);
        }

        if (! $user->profileConfirmationIsDue() || $user->hasDeferredProfileConfirmation()) {
            return $next($request);
        }

        return redirect()->route('profile.confirm');
    }
}
