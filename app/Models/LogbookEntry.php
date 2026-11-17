<?php

namespace App\Models;

use App\Casts\AsTimezone;
use Database\Factories\LogbookEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class LogbookEntry extends Model
{
    /** @use HasFactory<LogbookEntryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'booking_id',
        'occurred_on',
        'start_time',
        'timezone',
        'activity_label',
        'venue_name',
        'role',
        'hours',
        'participant_count',
        'weather',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'timezone' => AsTimezone::class,
            'hours' => 'decimal:2',
            'participant_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isLinkedToBooking(): bool
    {
        return $this->booking_id !== null;
    }

    public function applyBookingSnapshot(Booking $booking): void
    {
        $booking->loadMissing(['activity', 'venue']);

        $timezone = $booking->timezone ?? config('app.timezone');
        $start = $booking->start_at?->copy()->timezone($timezone);

        $this->occurred_on = $start?->toDateString();
        $this->start_time = $start?->format('H:i:s');
        $this->timezone = $timezone;
        $this->activity_label = filled($booking->activityTitle())
            ? $booking->activityTitle()
            : null;
        $this->venue_name = $booking->venue?->name;
    }

    public function applyDebriefFields(BookingDebrief $debrief): void
    {
        $this->hours = $debrief->hours;
        $this->participant_count = $debrief->participant_count;
        $this->weather = $debrief->weather;
    }

    public function activityTitle(): string
    {
        if ($this->booking !== null) {
            $title = $this->booking->activityTitle();

            if (filled($title)) {
                return $title;
            }
        }

        return (string) ($this->activity_label ?? '');
    }

    public function venueName(): string
    {
        if ($this->booking?->venue !== null) {
            return (string) $this->booking->venue->name;
        }

        return (string) ($this->venue_name ?? '');
    }

    public function sessionTimezone(): string
    {
        if ($this->booking?->timezone !== null) {
            return (string) $this->booking->timezone;
        }

        if ($this->timezone !== null) {
            return (string) $this->timezone;
        }

        return (string) config('app.timezone');
    }

    public function sessionStart(): ?Carbon
    {
        if ($this->booking?->start_at !== null) {
            return $this->booking->start_at->copy()->timezone($this->sessionTimezone());
        }

        if ($this->occurred_on === null) {
            return null;
        }

        $date = $this->occurred_on->toDateString();
        $time = $this->hasStartTime()
            ? substr((string) $this->start_time, 0, 8)
            : '00:00:00';

        return Carbon::parse($date.' '.$time, $this->sessionTimezone());
    }

    public function hasStartTime(): bool
    {
        if ($this->booking?->start_at !== null) {
            return true;
        }

        return filled($this->start_time);
    }

    public function scheduleLabel(): string
    {
        $start = $this->sessionStart();

        if ($start === null) {
            return '—';
        }

        $label = $start->toFormattedDayDateString();

        if (! $this->hasStartTime()) {
            return $label;
        }

        return $label.' '.$start->format('H:i');
    }
}
