<?php

namespace App\Models;

use App\Casts\AsSequence;
use App\Casts\AsTimezone;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use Carbon\Carbon;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use Concerns\HasSequence, Concerns\HasUid, HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'activity_id',
        'venue_id',
        'start_at',
        'end_at',
        'timezone',
        'status',
        'group_name',
        'notes',
        'lead_instructor_id',
        'lead_instructor_notes',
    ];

    protected $attributes = [
        'status' => BookingStatus::Tentative->value,
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => BookingStatus::class,
            'sequence' => AsSequence::class,
            'timezone' => AsTimezone::class,
        ];
    }

    protected function sequenced(): array
    {
        return [
            'start_at',
            'end_at',
            'venue_id',
            'status',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('comment', 'status', 'token')->as('attendance')
            ->using(BookingAttendance::class);
    }

    public function leadInstructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_instructor_id');
    }

    public function isPast(): bool
    {
        return $this->end_at?->isPast() ?? false;
    }

    public function isCancelled(): bool
    {
        return $this->status === BookingStatus::Cancelled;
    }

    public function isConfirmed(): bool
    {
        return $this->status === BookingStatus::Confirmed;
    }

    public function isTentative(): bool
    {
        return $this->status === BookingStatus::Tentative;
    }

    public function scopeOrdered(Builder $bookings): void
    {
        $bookings->orderBy('start_at')->orderBy('end_at');
    }

    public function scopeNotCancelled(Builder $bookings): void
    {
        $bookings->whereNot('bookings.status', BookingStatus::Cancelled);
    }

    public function scopeFuture(Builder $bookings): void
    {
        $bookings->whereDate('end_at', '>=', Carbon::now());
    }

    /**
     * @param  BookingAttendeeStatus[]  $status
     */
    public function scopeAttendeeStatus(Builder $bookings, User $attendee, array $status = []): void
    {
        $bookings->whereHas('attendees', function (Builder $query) use ($attendee, $status) {
            $query->where('user_id', $attendee->id);
            if ($status === []) {
                $query->whereNot('status', BookingAttendeeStatus::Declined);
            } else {
                $query->whereIn('status', $status);
            }
        });
    }

    public function scopeForUser(Builder $bookings, User $user): void
    {
        if ($user->isGuest()) {
            $bookings->attendeeStatus($user);
        } elseif ($user->cannot('manage', Booking::class)) {
            $bookings->where(function (Builder $query) use ($user) {
                $query->attendeeStatus($user)
                    ->orWhereIn('status', [BookingStatus::Confirmed]);
            });
        }
    }
}
