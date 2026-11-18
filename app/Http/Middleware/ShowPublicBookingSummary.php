<?php

namespace App\Http\Middleware;

use App\Livewire\BookingPreview;
use App\Models\Booking;
use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowPublicBookingSummary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()
            || ! $request->routeIs('filament.teams.resources.bookings.view')
            || filled($request->query('comment'))
        ) {
            return $next($request);
        }

        $booking = Booking::query()->findOrFail($request->route('record'));
        $booking->loadMissing('team');

        $tenant = $request->route('tenant');
        $urlSlug = $tenant instanceof Team
            ? (string) $tenant->slug
            : (string) $tenant;

        if ($booking->team === null || (string) $booking->team->slug !== $urlSlug) {
            abort(404);
        }

        $request->route()->setParameter('booking', $booking);

        return app()->call([app(BookingPreview::class), '__invoke']);
    }
}
