<?php

namespace App\Support\Calendar;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\PastBookingAttendeeStatus;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Member\Resources\Tasks\TaskResource as MemberTaskResource;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Data\FetchInfo;

class ScheduleCalendarEvents
{
    public function __construct(
        private User $viewer,
        private ?Team $team = null,
        private string $tab = 'rota',
    ) {}

    /**
     * @return list<EventData>
     */
    public function events(FetchInfo $info): array
    {
        return [
            ...$this->bookingEvents($info),
            ...$this->taskEvents($info),
        ];
    }

    /**
     * @return list<EventData>
     */
    private function bookingEvents(FetchInfo $info): array
    {
        $query = Booking::query()
            ->with([
                'team',
                'activity',
                'venue',
                'attendees' => fn ($attendees) => $attendees->whereKey($this->viewer->getKey()),
            ]);

        $this->scopeTeam($query);

        if ($this->tab === 'rota') {
            $query
                ->notCancelled()
                ->attendeeStatus($this->viewer, [
                    BookingAttendeeStatus::Accepted,
                    BookingAttendeeStatus::Tentative,
                ]);
        } else {
            $query->forUser($this->viewer);
        }

        $info->overlapping($query, 'start_at', 'end_at');

        return $query->get()
            ->map(fn (Booking $booking): EventData => $this->bookingEvent($booking))
            ->all();
    }

    /**
     * @return list<EventData>
     */
    private function taskEvents(FetchInfo $info): array
    {
        $query = Task::query()
            ->with(['team', 'venue', 'attendance'])
            ->whereNotNull('due_at');

        $this->scopeTeam($query);

        if ($this->tab === 'rota') {
            $query->whereHas(
                'attendance',
                fn (Builder $attendance): Builder => $attendance
                    ->where('user_id', $this->viewer->getKey())
                    ->whereIn('status', array_map(
                        fn (TaskAttendeeStatus $status): string => $status->value,
                        TaskAttendeeStatus::onRota(),
                    )),
            );
        } else {
            $query->visibleTo($this->viewer);
        }

        $info->overlapping($query, 'due_at', 'due_at');

        return $query->get()
            ->map(fn (Task $task): EventData => $this->taskEvent($task))
            ->all();
    }

    private function bookingEvent(Booking $booking): EventData
    {
        $attendance = $this->viewerAttendance($booking);
        $classNames = self::bookingClassNames($booking, $attendance);
        $icon = $this->attendanceLeadingIcon($attendance);

        return EventData::make()
            ->id('booking-'.$booking->getKey())
            ->title(self::eventTitleHtml(
                $booking->activityTitle(),
                $icon,
                $booking->venue?->name,
            ), html: true)
            ->start($booking->start_at)
            ->end($booking->end_at)
            ->url(MemberBookingResource::viewUrl($booking))
            ->color($booking->team->brandColor()->getColor())
            ->tooltip(self::bookingTooltipHtml($booking, $attendance), html: true)
            ->extendedProps([
                'kind' => 'booking',
                'status' => $booking->status->value,
                'attendance' => $attendance?->value,
            ])
            ->extraProperties([
                'classNames' => $classNames,
                'display' => 'block',
            ]);
    }

    private function taskEvent(Task $task): EventData
    {
        $attendance = $task->attendance?->user_id === $this->viewer->getKey()
            ? ($task->attendance->status instanceof TaskAttendeeStatus
                ? $task->attendance->status
                : null)
            : null;
        $classNames = self::taskClassNames($task);
        $due = $task->due_at->copy()->startOfDay();
        $icon = self::taskLeadingIcon($task, $attendance);

        return EventData::make()
            ->id('task-'.$task->getKey())
            ->title(self::eventTitleHtml(
                $task->summary,
                $icon,
                $task->venue?->name,
            ), html: true)
            ->start($due)
            ->allDay()
            ->url(MemberTaskResource::viewUrl($task))
            ->color($task->team->brandColor()->getColor())
            ->tooltip(self::taskTooltipHtml($task, $attendance), html: true)
            ->extendedProps([
                'kind' => 'task',
                'status' => $task->status->value,
                'attendance' => $attendance instanceof TaskAttendeeStatus ? $attendance->value : null,
            ])
            ->extraProperties([
                'classNames' => $classNames,
                'display' => 'block',
            ]);
    }

    public static function titleWithLeadingIcon(string $title, ?string $icon): string
    {
        if ($icon === null || $icon === '') {
            return $title;
        }

        return '<span class="fi-fc-event-leading-icon" aria-hidden="true">'.e($icon).'</span> '.e($title);
    }

    /**
     * HTML title for calendar events: leading icon, activity/summary, optional venue line.
     */
    public static function eventTitleHtml(string $title, ?string $icon = null, ?string $subtitle = null): string
    {
        $heading = filled($icon)
            ? self::titleWithLeadingIcon($title, $icon)
            : e($title);

        if (! filled($subtitle)) {
            return $heading;
        }

        return $heading.'<div class="fi-fc-event-subtitle">'.e($subtitle).'</div>';
    }

    /**
     * @param  Builder<Booking>|Builder<Task>  $query
     */
    private function scopeTeam(Builder $query): void
    {
        if ($this->team instanceof Team) {
            $query->where('team_id', $this->team->getKey());
        }
    }

    private function viewerAttendance(Booking $booking): ?BookingAttendeeStatus
    {
        $attendee = $booking->attendees->firstWhere('id', $this->viewer->getKey());

        if ($attendee === null) {
            return null;
        }

        return self::bookingAttendeeStatus($attendee->attendance->status ?? null);
    }

    public static function bookingAttendeeStatus(mixed $status): ?BookingAttendeeStatus
    {
        if ($status instanceof BookingAttendeeStatus) {
            return $status;
        }

        if ($status instanceof PastBookingAttendeeStatus || $status instanceof BackedEnum) {
            return BookingAttendeeStatus::tryFrom((string) $status->value);
        }

        if (is_string($status) && $status !== '') {
            return BookingAttendeeStatus::tryFrom($status);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function bookingClassNames(Booking $booking, ?BookingAttendeeStatus $attendance): array
    {
        $classes = ['fc-event--booking'];

        if ($booking->status === BookingStatus::Tentative) {
            $classes[] = 'fc-event--tentative';
        }

        if ($booking->status === BookingStatus::Cancelled) {
            $classes[] = 'fc-event--cancelled';
        }

        if ($attendance instanceof BookingAttendeeStatus) {
            $classes[] = 'fc-event--attendance-'.$attendance->value;
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    public static function taskClassNames(Task $task): array
    {
        $classes = ['fc-event--task'];

        if ($task->status === TaskStatus::Cancelled || $task->status === TaskStatus::Completed) {
            $classes[] = 'fc-event--muted';
        }

        return $classes;
    }

    private function attendanceLeadingIcon(?BookingAttendeeStatus $attendance): ?string
    {
        return match ($attendance) {
            BookingAttendeeStatus::Accepted => '✓',
            BookingAttendeeStatus::Tentative => '?',
            BookingAttendeeStatus::NeedsAction => '○',
            BookingAttendeeStatus::Declined => '✕',
            default => null,
        };
    }

    /**
     * ✓ is reserved for completed work — accepted assignment must not look done.
     */
    public static function taskLeadingIcon(Task $task, ?TaskAttendeeStatus $attendance = null): string
    {
        if ($task->status === TaskStatus::Completed || $attendance === TaskAttendeeStatus::Completed) {
            return '✓';
        }

        if ($task->status === TaskStatus::Cancelled || $attendance === TaskAttendeeStatus::Declined) {
            return '✕';
        }

        if ($task->status === TaskStatus::InProcess || $attendance === TaskAttendeeStatus::InProcess) {
            return '↻';
        }

        return match ($attendance) {
            TaskAttendeeStatus::Tentative => '?',
            TaskAttendeeStatus::NeedsAction => '○',
            default => '☐',
        };
    }

    public static function bookingTooltipHtml(Booking $booking, ?BookingAttendeeStatus $attendance = null): string
    {
        return self::renderTooltip(self::bookingTooltipRows($booking, $attendance));
    }

    public static function taskTooltipHtml(Task $task, ?TaskAttendeeStatus $attendance = null): string
    {
        return self::renderTooltip(self::taskTooltipRows($task, $attendance));
    }

    /**
     * @return list<array{icon: Heroicon, text: string}>
     */
    public static function bookingTooltipRows(Booking $booking, ?BookingAttendeeStatus $attendance = null): array
    {
        $title = $booking->activityTitle();
        $team = $booking->team?->name ?? '';
        $rows = [
            ['icon' => Heroicon::OutlinedCalendarDays, 'text' => $title],
        ];

        if ($team !== '' && strcasecmp($team, $title) !== 0) {
            $rows[] = ['icon' => Heroicon::OutlinedUserGroup, 'text' => $team];
        }

        if (filled($booking->venue?->name)) {
            $rows[] = ['icon' => Heroicon::OutlinedMapPin, 'text' => (string) $booking->venue->name];
        }

        $rows[] = ['icon' => $booking->status->getIcon(), 'text' => $booking->status->getLabel()];

        if ($attendance instanceof BookingAttendeeStatus) {
            $rows[] = ['icon' => $attendance->getIcon(), 'text' => $attendance->getLabel()];
        }

        return $rows;
    }

    /**
     * @return list<array{icon: Heroicon, text: string}>
     */
    public static function taskTooltipRows(Task $task, ?TaskAttendeeStatus $attendance = null): array
    {
        $summary = $task->summary;
        $team = $task->team?->name ?? '';
        $rows = [
            ['icon' => Heroicon::OutlinedClipboardDocumentList, 'text' => $summary],
        ];

        if ($team !== '' && strcasecmp($team, $summary) !== 0) {
            $rows[] = ['icon' => Heroicon::OutlinedUserGroup, 'text' => $team];
        }

        if (filled($task->venue?->name)) {
            $rows[] = ['icon' => Heroicon::OutlinedMapPin, 'text' => (string) $task->venue->name];
        }

        $rows[] = ['icon' => $task->status->getIcon(), 'text' => $task->status->getLabel()];

        if ($attendance instanceof TaskAttendeeStatus) {
            $rows[] = ['icon' => $attendance->getIcon(), 'text' => $attendance->getLabel()];
        }

        return $rows;
    }

    /**
     * @param  list<array{icon: Heroicon, text: string}>  $rows
     */
    public static function renderTooltip(array $rows): string
    {
        return view('filament.calendar.event-tooltip', ['rows' => $rows])->render();
    }
}
