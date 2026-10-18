<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberManageTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Team) {
            session(['manage_team_id' => $tenant->getKey()]);
        }

        return $next($request);
    }
}
