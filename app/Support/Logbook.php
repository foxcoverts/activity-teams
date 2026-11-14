<?php

namespace App\Support;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\LogbookEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class Logbook
{
    /**
     * Accepted attendance with no entry, not ignored, session day on or before today.
     *
     * @return Builder<Booking>
     */
    public function outstandingQuery(User $user): Builder
    {
        return Booking::query()
            ->with(['activity', 'audience', 'venue', 'team', 'debrief'])
            ->whereHas(
                'attendees',
                fn (Builder $query) => $query
                    ->where('users.id', $user->getKey())
                    ->where('booking_user.status', BookingAttendeeStatus::Accepted->value)
                    ->whereNull('booking_user.logbook_ignored_at'),
            )
            ->whereDoesntHave(
                'logbookEntries',
                fn (Builder $query) => $query->where('user_id', $user->getKey()),
            )
            ->whereNotNull('end_at')
            ->orderByDesc('end_at');
    }

    /**
     * @return Collection<int, Booking>
     */
    public function outstanding(User $user): Collection
    {
        return $this->outstandingQuery($user)
            ->get()
            ->filter(fn (Booking $booking): bool => $this->sessionDayIsDue($booking))
            ->values();
    }

    public function outstandingCount(User $user): int
    {
        return $this->outstanding($user)->count();
    }

    /**
     * @return Builder<LogbookEntry>
     */
    public function entriesQuery(User $user): Builder
    {
        return LogbookEntry::query()
            ->whereBelongsTo($user)
            ->with(['booking.activity', 'booking.audience', 'booking.venue', 'booking.team'])
            ->whereHas('booking')
            ->join('bookings', 'bookings.id', '=', 'logbook_entries.booking_id')
            ->orderByDesc('bookings.end_at')
            ->select('logbook_entries.*');
    }

    public function sessionDayIsDue(Booking $booking): bool
    {
        if ($booking->end_at === null) {
            return false;
        }

        $timezone = $booking->timezone ?? config('app.timezone');
        $sessionDay = $booking->end_at->copy()->timezone($timezone)->startOfDay();

        return ! $sessionDay->gt(now($timezone)->startOfDay());
    }

    /**
     * @param  array{hours?: mixed, participant_count?: mixed, weather?: mixed, notes?: mixed}  $data
     */
    public function add(User $user, Booking $booking, array $data = [], bool $prefillFromDebrief = true): LogbookEntry
    {
        $this->assertCanManage($user, $booking);

        if (LogbookEntry::query()->where('user_id', $user->getKey())->where('booking_id', $booking->getKey())->exists()) {
            throw ValidationException::withMessages([
                'booking' => __('This session is already in your logbook.'),
            ]);
        }

        $entry = new LogbookEntry([
            'user_id' => $user->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => $data['hours'] ?? null,
            'participant_count' => $data['participant_count'] ?? null,
            'weather' => $data['weather'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($prefillFromDebrief && $this->fieldsAreBlank($entry)) {
            $booking->loadMissing('debrief');

            if ($booking->debrief !== null) {
                $entry->applyDebriefFields($booking->debrief);
            }

            if ($entry->hours === null) {
                $entry->hours = $booking->suggestedOperatingHours();
            }
        }

        $entry->save();

        $booking->attendees()->syncWithoutDetaching([
            $user->getKey() => [
                'logbook_ignored_at' => null,
            ],
        ]);

        return $entry;
    }

    /**
     * Prefill values for the add form from the debrief when present, else booking duration for hours.
     *
     * @return array{hours: mixed, participant_count: mixed, weather: mixed, notes: null, debrief_notes: ?string}
     */
    public function addFormState(Booking $booking): array
    {
        $booking->loadMissing('debrief');
        $debrief = $booking->debrief;

        return [
            'hours' => $debrief?->hours ?? $booking->suggestedOperatingHours(),
            'participant_count' => $debrief?->participant_count,
            'weather' => $debrief?->weather,
            'notes' => null,
            'debrief_notes' => $debrief?->notes,
        ];
    }

    /**
     * @param  array{hours?: mixed, participant_count?: mixed, weather?: mixed, notes?: mixed}  $data
     */
    public function update(User $user, LogbookEntry $entry, array $data): LogbookEntry
    {
        if ((string) $entry->user_id !== (string) $user->getKey()) {
            throw ValidationException::withMessages([
                'entry' => __('You can only edit your own logbook entries.'),
            ]);
        }

        $entry->fill([
            'hours' => $data['hours'] ?? null,
            'participant_count' => $data['participant_count'] ?? null,
            'weather' => $data['weather'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return $entry;
    }

    public function copyFromDebrief(User $user, LogbookEntry $entry): LogbookEntry
    {
        if ((string) $entry->user_id !== (string) $user->getKey()) {
            throw ValidationException::withMessages([
                'entry' => __('You can only edit your own logbook entries.'),
            ]);
        }

        $entry->loadMissing('booking.debrief');
        $debrief = $entry->booking?->debrief;

        if ($debrief === null) {
            throw ValidationException::withMessages([
                'entry' => __('This booking has no debrief to copy from.'),
            ]);
        }

        $entry->applyDebriefFields($debrief);
        $entry->save();

        return $entry;
    }

    public function ignore(User $user, Booking $booking): void
    {
        $this->acceptedAttendance($user, $booking);

        $booking->attendees()->syncWithoutDetaching([
            $user->getKey() => [
                'logbook_ignored_at' => now(),
            ],
        ]);
    }

    public function remove(User $user, LogbookEntry $entry): void
    {
        if ((string) $entry->user_id !== (string) $user->getKey()) {
            throw ValidationException::withMessages([
                'entry' => __('You can only remove your own logbook entries.'),
            ]);
        }

        DB::transaction(function () use ($user, $entry): void {
            $booking = $entry->booking;

            $entry->delete();

            if ($booking instanceof Booking) {
                $this->ignore($user, $booking);
            }
        });
    }

    public function decline(User $user, Booking $booking): void
    {
        try {
            (new RespondToBookingAction($booking))(
                $user,
                BookingAttendeeStatus::Declined,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'booking' => $exception->getMessage(),
            ]);
        }
    }

    public function canDecline(Booking $booking): bool
    {
        return $booking->acceptsInvites();
    }

    public function canAdd(User $user, Booking $booking): bool
    {
        $attendance = $booking->attendees()->find($user->getKey())?->attendance;

        if (! $attendance instanceof BookingAttendance || ! $attendance->isAccepted()) {
            return false;
        }

        if (! $this->sessionDayIsDue($booking)) {
            return false;
        }

        return ! LogbookEntry::query()
            ->where('user_id', $user->getKey())
            ->where('booking_id', $booking->getKey())
            ->exists();
    }

    /**
     * Stable export / table filter contract.
     *
     * @param  array{from?: mixed, to?: mixed, activity?: mixed, team?: mixed}  $filters
     * @return array{from?: string, to?: string, activity?: list<string>, team?: string}
     */
    public function normalisedFilters(array $filters): array
    {
        $from = filled($filters['from'] ?? null) ? Carbon::parse($filters['from'])->toDateString() : null;
        $to = filled($filters['to'] ?? null) ? Carbon::parse($filters['to'])->toDateString() : null;
        $team = filled($filters['team'] ?? null) ? (string) $filters['team'] : null;

        $activity = collect(Arr::wrap($filters['activity'] ?? []))
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => (string) $value)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $normalised = [];

        if ($from !== null) {
            $normalised['from'] = $from;
        }

        if ($to !== null) {
            $normalised['to'] = $to;
        }

        if ($activity !== []) {
            $normalised['activity'] = $activity;
        }

        if ($team !== null && $team !== '') {
            $normalised['team'] = $team;
        }

        return $normalised;
    }

    /**
     * @return array{from?: string, to?: string, activity?: list<string>, team?: string}
     */
    public function filtersFromRequest(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'activity' => ['nullable', 'array'],
            'activity.*' => ['string'],
            'team' => ['nullable', 'string'],
        ]);

        return $this->normalisedFilters($validated);
    }

    /**
     * @param  Builder<LogbookEntry>  $query
     * @param  array{from?: string, to?: string, activity?: list<string>, team?: string}  $filters
     * @return Builder<LogbookEntry>
     */
    public function applyEntryFilters(Builder $query, array $filters): Builder
    {
        $filters = $this->normalisedFilters($filters);

        if (isset($filters['from'])) {
            $query->where('bookings.start_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (isset($filters['to'])) {
            $query->where('bookings.start_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        if (isset($filters['activity'])) {
            $query->whereIn('bookings.activity_id', $filters['activity']);
        }

        if (isset($filters['team'])) {
            $query->where('bookings.team_id', $filters['team']);
        }

        return $query;
    }

    /**
     * Activities that already appear in the member's saved log.
     *
     * @return array<string, string>
     */
    public function activityFilterOptions(User $user): array
    {
        return Activity::query()
            ->whereIn(
                'id',
                LogbookEntry::query()
                    ->whereBelongsTo($user)
                    ->join('bookings', 'bookings.id', '=', 'logbook_entries.booking_id')
                    ->whereNotNull('bookings.activity_id')
                    ->select('bookings.activity_id'),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Teams that already appear in the member's saved log.
     *
     * @return array<string, string>
     */
    public function teamFilterOptions(User $user): array
    {
        return Team::query()
            ->whereIn(
                'id',
                LogbookEntry::query()
                    ->whereBelongsTo($user)
                    ->join('bookings', 'bookings.id', '=', 'logbook_entries.booking_id')
                    ->whereNotNull('bookings.team_id')
                    ->select('bookings.team_id'),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return [
            __('Date'),
            __('Time'),
            __('Hours'),
            __('Activity'),
            __('Venue'),
            __('Participants'),
            __('Weather'),
            __('Notes'),
        ];
    }

    /**
     * @return list<string|null>
     */
    public function exportRow(LogbookEntry $entry): array
    {
        $booking = $entry->booking;
        $timezone = $booking?->timezone ?? config('app.timezone');
        $start = $booking?->start_at?->copy()->timezone($timezone);

        return [
            $start?->toFormattedDateString(),
            $start?->format('H:i'),
            $entry->hours !== null ? (string) $entry->hours : null,
            $booking?->activityTitle(),
            $booking?->venue?->name,
            $entry->participant_count !== null ? (string) $entry->participant_count : null,
            $entry->weather,
            $entry->notes,
        ];
    }

    private function assertCanManage(User $user, Booking $booking): void
    {
        $attendance = $booking->attendees()->find($user->getKey())?->attendance;

        if (! $attendance instanceof BookingAttendance || ! $attendance->isAccepted()) {
            throw ValidationException::withMessages([
                'booking' => __('Confirm attendance on the booking first, or speak to the lead or team manager.'),
            ]);
        }

        if (! $this->sessionDayIsDue($booking)) {
            throw ValidationException::withMessages([
                'booking' => __('Logbook entries can be added from the session day onwards.'),
            ]);
        }
    }

    private function acceptedAttendance(User $user, Booking $booking): BookingAttendance
    {
        $attendance = $booking->attendees()->find($user->getKey())?->attendance;

        if (! $attendance instanceof BookingAttendance || ! $attendance->isAccepted()) {
            throw ValidationException::withMessages([
                'booking' => __('Confirm attendance on the booking first, or speak to the lead or team manager.'),
            ]);
        }

        return $attendance;
    }

    private function fieldsAreBlank(LogbookEntry $entry): bool
    {
        return $entry->hours === null
            && $entry->participant_count === null
            && blank($entry->weather)
            && blank($entry->notes);
    }
}
