<?php

namespace App\Http\Controllers;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingIcsFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingIcsController extends Controller
{
    public function index(Request $request, User $user): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $bookings = Booking::query()->forUser($request->user())->with(['activity', 'venue', 'related'])->get();

        return $this->download(
            $bookings,
            __(':Name Bookings', ['name' => config('app.name')]),
            'bookings.ics',
        );
    }

    public function rota(Request $request, User $user): Response
    {
        Gate::authorize('viewOwn', Booking::class);

        $bookings = Booking::query()
            ->attendeeStatus($request->user(), [
                BookingAttendeeStatus::Accepted,
                BookingAttendeeStatus::Tentative,
            ])
            ->with(['activity', 'venue', 'related'])
            ->get();

        return $this->download(
            $bookings,
            __(':Name Rota', ['name' => config('app.name')]),
            'rota.ics',
        );
    }

    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        return $this->download(
            collect([$booking->load(['activity', 'venue', 'related'])]),
            __(':Name Bookings', ['name' => config('app.name')]),
            'booking-'.$booking->id.'.ics',
        );
    }

    protected function download(iterable $bookings, string $title, string $filename): Response
    {
        $body = app(BookingIcsFactory::class)->make($bookings, $title);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
