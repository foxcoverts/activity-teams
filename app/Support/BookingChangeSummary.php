<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;

class BookingChangeSummary
{
    /**
     * @param  list<string>  $changes
     * @param  array{start_at: mixed, end_at: mixed, timezone: mixed}|null  $previousSchedule
     */
    public function __construct(
        private Booking $booking,
        private array $changes,
        private ?array $previousSchedule,
    ) {
        if ($this->has('lead_instructor_id')) {
            $this->booking->loadMissing('leadInstructor');
        }
    }

    public function summary(): ?string
    {
        $labels = $this->labels();

        if ($labels === []) {
            return null;
        }

        return collect($labels)->join(', ', ' and ');
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ([
            'activity_id' => __('Activity'),
            'audience_id' => __('Audience'),
            'venue_id' => __('Venue'),
            'group_name' => __('Group'),
        ] as $attribute => $label) {
            if ($this->has($attribute)) {
                $labels[] = $label;
            }
        }

        array_push($labels, ...$this->scheduleLabels());

        foreach ([
            'notes' => __('Notes'),
            'lead_instructor_id' => __('Lead instructor'),
            'status' => __('Status'),
        ] as $attribute => $label) {
            if ($this->has($attribute)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    public function scheduleLabels(): array
    {
        $keys = array_values(array_intersect($this->changes, ['start_at', 'end_at', 'timezone']));

        if ($keys === [] || $this->previousSchedule === null) {
            return [];
        }

        $start = localDate($this->booking->start_at, $this->booking->timezone);
        $end = localDate($this->booking->end_at, $this->booking->timezone);

        if (! $start->isSameDay($end)) {
            $labels = [];

            if (array_intersect($keys, ['start_at', 'timezone']) !== []) {
                $labels[] = __('Start');
            }

            if (array_intersect($keys, ['end_at', 'timezone']) !== []) {
                $labels[] = __('End');
            }

            return $labels;
        }

        $previousStart = localDate($this->previousSchedule['start_at'], $this->previousSchedule['timezone']);
        $previousEnd = localDate($this->previousSchedule['end_at'], $this->previousSchedule['timezone']);
        $labels = [];

        if (
            array_intersect($keys, ['start_at', 'timezone']) !== []
            && ! $previousStart->isSameDay($start)
        ) {
            $labels[] = __('Date');
        }

        $timeChanged = $previousStart->format('H:i') !== $start->format('H:i')
            || $previousEnd->format('H:i') !== $end->format('H:i');

        if ($timeChanged) {
            $labels[] = __('Time');
        }

        return $labels;
    }

    public function has(string $attribute): bool
    {
        return in_array($attribute, $this->changes, true);
    }

    public function suffix(string $attribute): string
    {
        return $this->has($attribute) ? ' '.__('(changed)') : '';
    }

    public function leadInstructorName(): ?string
    {
        return $this->booking->leadInstructor?->name;
    }

    public function statusLabel(): string
    {
        return match ($this->booking->status) {
            BookingStatus::Confirmed => __('Confirmed'),
            BookingStatus::Tentative => __('Tentative'),
            BookingStatus::Cancelled => __('Cancelled'),
        };
    }
}
