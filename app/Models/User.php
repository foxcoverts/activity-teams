<?php

namespace App\Models;

use App\Casts\AsTimezone;
use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection;
use App\Enums\Section;
use Database\Factories\UserFactory;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Concerns\HasUid, HasFactory, HasUlids, LogsActivity, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'emergency_name',
        'emergency_phone',
        'password',
        'timezone',
        'section',
        'suspended',
        'oversight',
        'is_system_admin',
        'ical_token',
        'privacy_notice_accepted_at',
        'profile_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'section' => Section::Adult->value,
        'suspended' => false,
        'oversight' => false,
        'is_system_admin' => false,
        'privacy_notice_accepted_at' => null,
        'profile_confirmed_at' => null,
        'deleted_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'privacy_notice_accepted_at' => 'datetime',
            'profile_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'section' => Section::class,
            'suspended' => 'boolean',
            'oversight' => 'boolean',
            'is_system_admin' => 'boolean',
            'timezone' => AsTimezone::class,
        ];
    }

    protected static function booted(): void
    {
        // Mirrors the database constraint: a person without an email address cannot sign in.
        static::saving(function (User $user): void {
            if (blank($user->email)) {
                $user->email = null;
                $user->password = null;
            }
        });

        static::deleting(function (User $user): void {
            if (! $user->isForceDeleting()) {
                return;
            }

            $user->qualifications()->get()->each->delete();
        });
    }

    public function routeNotificationForMail(): array|string|null
    {
        return filled($this->email) ? [$this->email => $this->name] : null;
    }

    public function sendEmailVerificationNotification(): void
    {
        $notification = new VerifyEmail;
        $notification->url = Filament::getPanel('member')->getVerifyEmailUrl($this);

        $this->notify($notification);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', ...PersonalDataSection::protectedAttributes()])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->dontLogIfAttributesChangedOnly(['remember_token', 'password', ...PersonalDataSection::protectedAttributes()]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->trashed() || ! $this->isActive() || $this->isSuspended()) {
            return false;
        }

        return match ($panel->getId()) {
            'manage' => $this->isSystemAdmin() || $this->managedTeams()->exists(),
            'admin' => $this->isSystemAdmin() || $this->hasOversight(),
            default => true,
        };
    }

    public function getTenants(Panel $panel): EloquentCollection
    {
        if ($panel->getId() !== 'manage') {
            return new EloquentCollection;
        }

        if ($this->isSystemAdmin()) {
            return Team::query()->orderBy('name')->get();
        }

        return $this->managedTeams()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Team || $this->isSuspended()) {
            return false;
        }

        return $this->isSystemAdmin() || $this->manages($tenant);
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        $tenants = $this->getTenants($panel);
        $remembered = session('manage_team_id');

        if (filled($remembered)) {
            $match = $tenants->firstWhere('id', $remembered);

            if ($match instanceof Team) {
                return $match;
            }
        }

        return $tenants->first();
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class)
            ->withTimestamps()
            ->withPivot('comment', 'status', 'token')->as('attendance')
            ->using(BookingAttendance::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function ledBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'lead_instructor_id');
    }

    public function keyRings(): HasMany
    {
        return $this->hasMany(KeyRing::class, 'holder_id')->orderBy('name');
    }

    public function holdsKey(Key $key): bool
    {
        return $this->keyRings()
            ->where(function (Builder $rings) use ($key): void {
                $rings->whereHas('keys', fn (Builder $keys) => $keys->whereKey($key->getKey()))
                    ->orWhereHas('keys.covers', fn (Builder $covered) => $covered->whereKey($key->getKey()));
            })
            ->exists();
    }

    public function scopeHolding(Builder $users, Key $key): void
    {
        $users->whereHas(
            'keyRings',
            fn (Builder $rings) => $rings->where(function (Builder $rings) use ($key): void {
                $rings->whereHas('keys', fn (Builder $keys) => $keys->whereKey($key->getKey()))
                    ->orWhereHas('keys.covers', fn (Builder $covered) => $covered->whereKey($key->getKey()));
            }),
        );
    }

    /**
     * @param  iterable<int, Key>  $keys
     * @return list<string>
     */
    public function namesOfOpenedKeys(iterable $keys): array
    {
        $wanted = collect($keys)->filter(fn (mixed $key): bool => $key instanceof Key)->values();

        if ($wanted->isEmpty()) {
            return [];
        }

        $this->loadMissing('keyRings.keys.covers');

        return $wanted
            ->filter(fn (Key $key): bool => $this->opensLoadedKey($key))
            ->sortBy(fn (Key $key): string => $key->name)
            ->values()
            ->map(fn (Key $key): string => $key->name)
            ->all();
    }

    /**
     * @return Builder<Key>
     */
    public function openedKeys(): Builder
    {
        return $this->openedKeysQuery();
    }

    /**
     * @return Builder<Key>
     */
    public function openedKeysQuery(?Team $team = null): Builder
    {
        $keys = Key::query()->openedBy($this)->orderBy('name');

        if ($team instanceof Team) {
            $keys->relevantTo($team);
        }

        return $keys;
    }

    private function opensLoadedKey(Key $key): bool
    {
        foreach ($this->keyRings as $ring) {
            foreach ($ring->keys as $held) {
                if ($held->is($key)) {
                    return true;
                }

                if ($held->covers->contains(fn (Key $covered): bool => $covered->is($key))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(TeamInvitation::class);
    }

    /**
     * A named person added by a team manager who has not yet taken over the name with an account.
     */
    public function isPlaceholder(): bool
    {
        return blank($this->email) && ! $this->trashed();
    }

    public static function leftRecordMessage(): string
    {
        return __('This person has left. Their record is kept for history and cannot be changed.');
    }

    public function profileConfirmationIsDue(): bool
    {
        if ($this->isPlaceholder() || $this->trashed() || ! $this->isActive()) {
            return false;
        }

        $lastConfirmed = $this->profile_confirmed_at ?? $this->created_at;

        return $lastConfirmed === null || $lastConfirmed->lte(now()->subYear());
    }

    public function hasDeferredProfileConfirmation(): bool
    {
        $deferredOn = session('profile_confirmation_deferred_on');

        if (! is_string($deferredOn)) {
            return false;
        }

        return $deferredOn === now($this->timezoneName())->toDateString();
    }

    public function deferProfileConfirmation(): void
    {
        session(['profile_confirmation_deferred_on' => now($this->timezoneName())->toDateString()]);
    }

    public function timezoneName(): string
    {
        return $this->timezone?->getName() ?? (string) config('app.timezone');
    }

    public function isLastSystemAdmin(): bool
    {
        if (! $this->is_system_admin) {
            return false;
        }

        return ! static::query()
            ->whereKeyNot($this->getKey())
            ->where('is_system_admin', true)
            ->where('suspended', false)
            ->whereNotNull('password')
            ->exists();
    }

    public function invitationStatus(): InvitationStatus
    {
        if (! $this->isPlaceholder()) {
            return InvitationStatus::Active;
        }

        $invitation = $this->invitation;

        return match (true) {
            $invitation === null, $invitation->isRevoked() => InvitationStatus::NotInvited,
            $invitation->isDeclined() => InvitationStatus::Declined,
            $invitation->isExpired() => InvitationStatus::Expired,
            default => InvitationStatus::Invited,
        };
    }

    public function status(): AccountStatus
    {
        if ($this->trashed()) {
            return AccountStatus::Deleted;
        }

        if ($this->isSuspended()) {
            return AccountStatus::Suspended;
        }

        return AccountStatus::from($this->invitationStatus()->value);
    }

    /**
     * @return array<int, AccountRole>
     */
    public function roles(): array
    {
        $roles = [];
        if ($this->isSystemAdmin()) {
            $roles[] = AccountRole::SystemAdmin;
        }
        if ($this->hasOversight()) {
            $roles[] = AccountRole::Oversight;
        }

        return $roles;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->withPivot(['id', 'role', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at')
            ->using(TeamMembership::class);
    }

    public function managedTeams(): BelongsToMany
    {
        return $this->teams()->wherePivot('role', MembershipRole::Manager->value);
    }

    public function manages(Team $team): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return $this->managedTeams()->whereKey($team->getKey())->exists();
    }

    public function canInviteTo(Team $team): bool
    {
        return $this->isSystemAdmin() || $this->manages($team);
    }

    public function canInviteSiteAccess(): bool
    {
        return $this->isSystemAdmin();
    }

    /**
     * Teams that would have no active (unsuspended) manager if this person were suspended.
     *
     * @return EloquentCollection<int, Team>
     */
    public function teamsLeftWithoutActiveManager(): EloquentCollection
    {
        return $this->managedTeams()->get()->filter(
            fn (Team $team): bool => ! $team->managers()
                ->where('users.id', '!=', $this->getKey())
                ->where('users.suspended', false)
                ->exists()
        )->values();
    }

    public function isCurrentMemberOf(Team $team): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return $this->teams()->whereKey($team->getKey())->exists();
    }

    public function roleOn(Team|string $team): ?MembershipRole
    {
        $teamId = $team instanceof Team ? $team->getKey() : $team;

        $role = $this->relationLoaded('teams')
            ? $this->teams->find($teamId)?->pivot?->role
            : $this->teams()->whereKey($teamId)->first()?->pivot?->role;

        return $role instanceof MembershipRole ? $role : MembershipRole::tryFrom((string) $role);
    }

    public function teamRole(): ?MembershipRole
    {
        $this->loadMissing('teams');

        if ($this->teams->contains(fn (Team $team): bool => $team->pivot->role === MembershipRole::Manager)) {
            return MembershipRole::Manager;
        }

        return $this->teams->isNotEmpty() ? MembershipRole::Member : null;
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }

    public function hasAcknowledgedPersonalData(User $subject, PersonalDataSection $section): bool
    {
        if ($this->is($subject)) {
            return true;
        }

        return PersonalDataAcknowledgement::query()
            ->current()
            ->where('viewer_id', $this->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('section', $section)
            ->exists();
    }

    public function isActive(): bool
    {
        return filled($this->password);
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    public function hasOversight(): bool
    {
        return $this->oversight && ! $this->isSuspended();
    }

    public function isSystemAdmin(): bool
    {
        return $this->is_system_admin && ! $this->isSuspended();
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
}
