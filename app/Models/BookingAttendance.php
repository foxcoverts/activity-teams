<?php

namespace App\Models;

use App\Enums\BookingAttendeeStatus;
use App\Enums\InvitationNudgeTier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class BookingAttendance extends Pivot
{
    protected $table = 'booking_user';

    public $incrementing = false;

    protected $fillable = [
        'status',
        'comment',
        'token',
        'calendar_imip_at',
        'invitation_nudge_tier',
        'session_reminded_at',
    ];

    protected $attributes = [
        'status' => BookingAttendeeStatus::NeedsAction->value,
        'comment' => null,
    ];

    protected static function booted(): void
    {
        static::saving(function (BookingAttendance $attendance): void {
            if (blank($attendance->token)) {
                $attendance->token = static::generateToken();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingAttendeeStatus::class,
            'calendar_imip_at' => 'datetime',
            'invitation_nudge_tier' => InvitationNudgeTier::class,
            'session_reminded_at' => 'datetime',
        ];
    }

    public function hasCalendarChannel(): bool
    {
        return $this->calendar_imip_at !== null;
    }

    public static function build(Booking $booking, User $user): static
    {
        $attendance = new static;
        $attendance->booking = $booking;
        $attendance->user = $user;

        return $attendance;
    }

    public static function generateToken(): string
    {
        return hash('sha256', sprintf(
            '%s%s%s',
            config('app.token_prefix', ''),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        ));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAccepted(): bool
    {
        return $this->status === BookingAttendeeStatus::Accepted;
    }

    public function isDeclined(): bool
    {
        return $this->status === BookingAttendeeStatus::Declined;
    }

    public function needsAction(): bool
    {
        return $this->status === BookingAttendeeStatus::NeedsAction;
    }

    public function isLeadInstructor(): bool
    {
        $leadInstructorId = match (true) {
            $this->relationLoaded('booking') => $this->booking->lead_instructor_id,
            $this->pivotParent instanceof Booking => $this->pivotParent->lead_instructor_id,
            default => $this->booking()->value('lead_instructor_id'),
        };

        return $this->user_id === $leadInstructorId;
    }
}
