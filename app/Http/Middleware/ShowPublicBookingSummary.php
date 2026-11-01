<?php

namespace App\Http\Middleware;

use App\Livewire\BookingPreview;
use App\Models\Booking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowPublicBookingSummary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()
            || ! $request->routeIs('filament.member.resources.bookings.view')
            || filled($request->query('comment'))
        ) {
            return $next($request);
        }

        $booking = Booking::query()->findOrFail($request->route('record'));

        $request->route()->setParameter('booking', $booking);

        return app()->call([app(BookingPreview::class), '__invoke']);
    }
}
