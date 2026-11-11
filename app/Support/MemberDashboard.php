<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\TaskAttendeeStatus;
use App\Models\Booking;
use App\Models\InboxNotification;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads for the member and team dashboards; pass a team to limit bookings and news to it. Booking days are decided by each booking's own timezone.
 */
class MemberDashboard
{
    public const PREVIEW_LIMIT = 5;

    public const WINDOW_DAYS = 7;

    public const MAYBE_MONTHS = 3;

    public const NEWS_MAX_AGE_DAYS = 30;

    public function __construct(public User $user, public ?Team $team = null) {}

    /**
     * @return array{rows: Collection<int, Booking>, total: int}
     */
    public function thisWeek(): array
    {
        $all = $this->bookingsQuery()
            ->where('end_at', '>=', now()->subDay())
            ->where('start_at', '<', now()->addDays(self::WINDOW_DAYS + 1))
            ->where(function (Builder $query): void {
                $query->attendeeStatus($this->user, [
                    BookingAttendeeStatus::Accepted,
                    BookingAttendeeStatus::Tentative,
                    BookingAttendeeStatus::NeedsAction,
                ])->orWhere(function (Builder $led): void {
                    $led->where('lead_instructor_id', $this->user->getKey())
                        ->whereDoesntHave('attendees', fn (Builder $attendees) => $attendees
                            ->where('users.id', $this->user->getKey())
                            ->where('status', BookingAttendeeStatus::Declined));
                });
            })
            ->get()
            ->filter(fn (Booking $booking): bool => $this->inWindow($booking))
            ->values();

        return ['rows' => $all->take(self::PREVIEW_LIMIT)->values(), 'total' => $all->count()];
    }

    /**
     * @return array{awaiting: Collection<int, Booking>, awaitingTotal: int, maybe: Collection<int, Booking>, maybeTotal: int}
     */
    public function needsResponse(): array
    {
        $awaiting = $this->outstandingAfterWindow(BookingAttendeeStatus::NeedsAction);
        $maybe = $this->outstandingAfterWindow(BookingAttendeeStatus::Tentative)
            ->filter(fn (Booking $booking): bool => $booking->start_at->lte(now()->addMonths(self::MAYBE_MONTHS)))
            ->values();

        return [
            'awaiting' => $awaiting->take(self::PREVIEW_LIMIT)->values(),
            'awaitingTotal' => $awaiting->count(),
            'maybe' => $maybe->take(self::PREVIEW_LIMIT)->values(),
            'maybeTotal' => $maybe->count(),
        ];
    }

    /**
     * Every confirmed booking the member leads on the day of their next one, unless that day is inside the this-week window.
     *
     * @return Collection<int, Booking>
     */
    public function nextLedDay(): Collection
    {
        $leads = $this->bookingsQuery()
            ->where('status', BookingStatus::Confirmed)
            ->where('lead_instructor_id', $this->user->getKey())
            ->where('end_at', '>=', now())
            ->get();

        $shown = $this->needsResponse();
        $excluded = $shown['awaiting']->pluck('id')->merge($shown['maybe']->pluck('id'));
        $leads = $leads->reject(fn (Booking $booking): bool => $excluded->contains($booking->getKey()))->values();

        $first = $leads->first();

        if (! $first instanceof Booking || $this->inWindow($first)) {
            return collect();
        }

        $day = $this->localDay($first);

        return $leads
            ->filter(fn (Booking $booking): bool => $this->localDay($booking) === $day)
            ->values();
    }

    /**
     * Open tasks assigned to the member (including needs-action invites). Overdue rows are exclusive to that block.
     *
     * @return array{overdue: Collection<int, Task>, overdueTotal: int, assigned: Collection<int, Task>, assignedTotal: int}
     */
    public function assignedTasks(): array
    {
        $active = array_map(
            fn (TaskAttendeeStatus $status): string => $status->value,
            TaskAttendeeStatus::activeAssignments(),
        );

        $base = Task::query()
            ->visibleTo($this->user)
            ->open()
            ->when($this->team instanceof Team, fn (Builder $query) => $query->forTeam($this->team))
            ->whereHas(
                'attendance',
                fn (Builder $attendance): Builder => $attendance
                    ->where('user_id', $this->user->getKey())
                    ->whereIn('status', $active),
            )
            ->with([
                'team',
                'venue',
                'attendance' => fn ($attendance) => $attendance->where('user_id', $this->user->getKey()),
            ]);

        $overdueAll = (clone $base)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get();

        $overdueIds = $overdueAll->modelKeys();

        $assignedAll = (clone $base)
            ->when($overdueIds !== [], fn (Builder $query) => $query->whereKeyNot($overdueIds))
            ->orderedForList()
            ->get();

        return [
            'overdue' => $overdueAll->take(self::PREVIEW_LIMIT)->values(),
            'overdueTotal' => $overdueAll->count(),
            'assigned' => $assignedAll->take(self::PREVIEW_LIMIT)->values(),
            'assignedTotal' => $assignedAll->count(),
        ];
    }

    public function hasAttention(): bool
    {
        $needs = $this->needsResponse();
        $tasks = $this->assignedTasks();

        return $this->thisWeek()['rows']->isNotEmpty()
            || $this->unreadPreview()->isNotEmpty()
            || $needs['awaiting']->isNotEmpty()
            || $needs['maybe']->isNotEmpty()
            || $tasks['overdue']->isNotEmpty()
            || $tasks['assigned']->isNotEmpty()
            || $this->nextLedDay()->isNotEmpty();
    }

    public function latestNews(): ?NewsPost
    {
        return NewsPost::query()
            ->with(['team'])
            ->forUser($this->user)->live()
            ->when($this->team instanceof Team, fn (Builder $query) => $query->forTeam($this->team))
            ->where('published_at', '>=', now()->subDays(self::NEWS_MAX_AGE_DAYS))
            ->orderByDesc('published_at')
            ->first();
    }

    public function newsIsUnread(NewsPost $post): bool
    {
        return $this->user->unreadNotifications()
            ->where('data->subject_type', NewsPost::class)
            ->where('data->subject_id', $post->getKey())
            ->exists();
    }

    /**
     * @return Collection<int, InboxNotification>
     */
    public function unreadPreview(): Collection
    {
        return InboxNotification::query()
            ->where('notifiable_type', $this->user->getMorphClass())
            ->where('notifiable_id', $this->user->getKey())
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    /**
     * @param  array<int, string>  $ids
     */
    public function markRead(array $ids): void
    {
        InboxNotification::query()
            ->where('notifiable_type', $this->user->getMorphClass())
            ->where('notifiable_id', $this->user->getKey())
            ->whereNull('read_at')
            ->whereKey($ids)
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }

    public function markAllRead(): void
    {
        InboxNotification::query()
            ->where('notifiable_type', $this->user->getMorphClass())
            ->where('notifiable_id', $this->user->getKey())
            ->whereNull('read_at')
            ->each(fn (InboxNotification $notification) => $notification->markReadForDelivery());
    }

    public function windowStart(Booking $booking): Carbon
    {
        return now()->setTimezone($booking->timezone)->startOfDay();
    }

    public function inWindow(Booking $booking): bool
    {
        $start = $this->windowStart($booking);

        return $booking->end_at->gte($start)
            && $booking->start_at->lt($start->copy()->addDays(self::WINDOW_DAYS));
    }

    private function localDay(Booking $booking): string
    {
        return $booking->start_at->copy()->setTimezone($booking->timezone)->toDateString();
    }

    /**
     * @return Collection<int, Booking>
     */
    private function outstandingAfterWindow(BookingAttendeeStatus $status): Collection
    {
        return $this->bookingsQuery()
            ->where('end_at', '>=', now())
            ->where('start_at', '>=', now()->addDays(self::WINDOW_DAYS - 1))
            ->attendeeStatus($this->user, [$status])
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->start_at->gte(
                $this->windowStart($booking)->addDays(self::WINDOW_DAYS)
            ))
            ->values();
    }

    /**
     * @return Builder<Booking>
     */
    private function bookingsQuery(): Builder
    {
        return Booking::query()
            ->forUser($this->user)
            ->notCancelled()
            ->when($this->team instanceof Team, fn (Builder $query) => $query->where('bookings.team_id', $this->team->getKey()))
            ->with([
                'team',
                'activity',
                'audience',
                'venue',
                'attendees' => fn ($attendees) => $attendees->where('users.id', $this->user->getKey()),
            ])
            ->ordered();
    }
}
