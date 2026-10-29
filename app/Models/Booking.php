<?php

namespace App\Models;

use App\Casts\AsSequence;
use App\Casts\AsTimezone;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Support\Coords;
use App\Support\Location;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use Concerns\HasSequence, Concerns\HasUid, HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'team_id',
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

    public ?string $cancellationReason = null;

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

    protected static function booted(): void
    {
        static::saving(function (Booking $booking): void {
            $tenant = Filament::getTenant();

            if (blank($booking->team_id) && $tenant instanceof Team) {
                $booking->team_id = $tenant->getKey();
            }

            if (blank($booking->team_id)) {
                throw ValidationException::withMessages([
                    'team_id' => __('Choose a team.'),
                ]);
            }

            $team = Team::query()->find($booking->team_id);

            if ($team === null) {
                return;
            }

            if (filled($booking->activity_id) && ! $team->activities()->whereKey($booking->activity_id)->exists()) {
                throw ValidationException::withMessages([
                    'activity_id' => __('Choose an activity this team has been given.'),
                ]);
            }

            if (filled($booking->venue_id) && ! $team->venues()->whereKey($booking->venue_id)->exists()) {
                throw ValidationException::withMessages([
                    'venue_id' => __('Choose a venue this team has been given.'),
                ]);
            }
        });
    }

    /**
     * iCalendar SEQUENCE revisions (RFC 5545): start, end, and status must
     * increment it. A location or activity change counts as one too.
     */
    protected function sequenced(): array
    {
        return [
            'activity_id',
            'start_at',
            'end_at',
            'venue_id',
            'status',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->activityDescription($eventName));
    }

    public function activityDescription(string $eventName): string
    {
        $previous = $this->oldAttributes['status'] ?? null;
        $previous = $previous instanceof BookingStatus ? $previous->value : $previous;

        if (
            $eventName === 'updated'
            && $this->status === BookingStatus::Cancelled
            && $previous !== BookingStatus::Cancelled->value
            && filled($this->cancellationReason)
        ) {
            return $this->cancellationReason;
        }

        return $eventName;
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activities(): MorphMany
    {
        return $this->activitiesAsSubject()->with('causer')->latest();
    }

    public function isLead(User $user): bool
    {
        return $this->lead_instructor_id !== null
            && $this->lead_instructor_id === $user->getKey();
    }

    /**
     * @return Collection<int, User>
     */
    public function acceptedAttendees(): Collection
    {
        $this->loadMissing('attendees');

        return $this->attendees
            ->filter(fn (User $attendee): bool => $attendee->attendance?->status === BookingAttendeeStatus::Accepted)
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public function leadOptions(): array
    {
        $this->loadMissing('activity');
        $activity = $this->activity;

        return $this->acceptedAttendees()
            ->filter(fn (User $user): bool => ! $activity instanceof Activity || $activity->metBy($user, $this))
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
            ->all();
    }

    public function qualificationAsOf(): CarbonInterface
    {
        return $this->end_at->copy()->timezone($this->timezone)->startOfDay();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Keys marked required for this booking's activity at its venue.
     * A key that covers one of these is not itself required.
     *
     * @return EloquentCollection<int, Key>
     */
    public function requiredKeys(): EloquentCollection
    {
        if (blank($this->activity_id) || blank($this->venue_id)) {
            return new EloquentCollection;
        }

        return Key::query()
            ->whereHas('requirements', function (Builder $requirements): void {
                $requirements
                    ->where('activity_id', $this->activity_id)
                    ->where('venue_id', $this->venue_id);
            })
            ->orderBy('name')
            ->get();
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('comment', 'status', 'token')->as('attendance')
            ->using(BookingAttendance::class);
    }

    /**
     * @return BelongsToMany<Booking, $this>
     */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'booking_related', 'booking_id', 'related_id')
            ->with('activity')
            ->orderBy('bookings.start_at')
            ->orderBy('bookings.end_at');
    }

    public function relate(Booking $other): void
    {
        if ((string) $this->getKey() === (string) $other->getKey()) {
            return;
        }

        if ((string) $this->team_id !== (string) $other->team_id) {
            throw ValidationException::withMessages([
                'related_id' => __('Choose a booking from this team.'),
            ]);
        }

        $this->related()->syncWithoutDetaching([$other->getKey()]);
        $other->related()->syncWithoutDetaching([$this->getKey()]);
    }

    public function unrelate(Booking $other): void
    {
        $this->related()->detach($other->getKey());
        $other->related()->detach($this->getKey());
    }

    public function relatedLabel(): string
    {
        $this->loadMissing('activity');

        $start = $this->start_at->copy()
            ->timezone($this->timezone)
            ->locale(config('app.locale'));

        return $start->isoFormat('ddd D MMM YYYY HH:mm').' · '.$this->activity->name.' · '.$this->group_name;
    }

    public function leadInstructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_instructor_id');
    }

    public function isPast(): bool
    {
        return $this->end_at?->isPast() ?? false;
    }

    public function acceptsInvites(): bool
    {
        return ! $this->isPast() && ! $this->isCancelled();
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function summary(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->activity->name.' - '.$this->start_at->timezone($this->timezone)->toFormattedDayDateString()
        );
    }

    /**
     * Get the name of the booking location.
     */
    public function location(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Location => $this->venue?->location
        );
    }

    public function coords(): Attribute
    {
        return Attribute::make(
            get: fn (): ?Coords => $this->venue?->coords
        );
    }

    public function scopeForUser(Builder $bookings, User $user): void
    {
        if ($user->isSuspended()) {
            $bookings->whereRaw('0 = 1');

            return;
        }

        $bookings->where(function (Builder $query) use ($user) {
            $query->attendeeStatus($user)
                ->orWhere(function (Builder $confirmed) use ($user) {
                    $confirmed->where('status', BookingStatus::Confirmed)
                        ->whereHas('team', function (Builder $teams) use ($user) {
                            $teams->whereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
                        });
                })
                ->orWhereHas('team', function (Builder $teams) use ($user) {
                    $teams->whereHas('managers', fn (Builder $managers) => $managers->whereKey($user->getKey()));
                });
        });
    }
}
