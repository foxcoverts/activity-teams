<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use App\Support\CombinedCalendarIcs;
use DateInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TaskIcsController extends Controller
{
    public function __construct(private CombinedCalendarIcs $calendar) {}

    public function tasks(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User && ! $viewer->isSuspended(), 403);

        $tasks = Task::query()
            ->visibleTo($viewer)
            ->with('bookings')
            ->orderedForList()
            ->get();

        return $this->download(
            $this->calendar->tasksOnly(
                $tasks,
                __(':Name Tasks', ['name' => config('app.name')]),
                DateInterval::createFromDateString('7 days') ?: null,
            ),
            'tasks.ics',
        );
    }

    public function everything(Request $request, User $user): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $viewer = $request->user();
        assert($viewer instanceof User);

        $bookings = Booking::query()
            ->forUser($viewer)
            ->with(['activity', 'venue', 'related'])
            ->get();

        $tasks = Task::query()
            ->visibleTo($viewer)
            ->with('bookings')
            ->orderedForList()
            ->get();

        return $this->download(
            $this->calendar->make(
                $bookings,
                $tasks,
                __(':Name Calendar', ['name' => config('app.name')]),
                DateInterval::createFromDateString('7 days') ?: null,
            ),
            'everything.ics',
        );
    }

    private function download(string $body, string $filename): Response
    {
        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
