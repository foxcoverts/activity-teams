<?php

namespace App\Models;

use App\Casts\AsTimezone;
use App\Enums\MembershipRole;
use App\Enums\Section;
use App\Notifications\SetupAccount;
use Database\Factories\UserFactory;
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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Concerns\HasUid, HasFactory, HasUlids, LogsActivity, Notifiable;

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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
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
        static::deleting(function (User $user): void {
            $user->qualifications()->with('detail')->get()->each->delete();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['remember_token', 'password']);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive() || $this->isSuspended()) {
            return false;
        }

        if ($panel->getId() === 'manage') {
            return $this->isSystemAdmin() || $this->hasOversight() || $this->managedTeams()->exists();
        }

        return true;
    }

    public function getTenants(Panel $panel): EloquentCollection
    {
        if ($panel->getId() !== 'manage') {
            return new EloquentCollection;
        }

        if ($this->isSystemAdmin() || $this->hasOversight()) {
            return Team::query()->orderBy('name')->get();
        }

        return $this->managedTeams()->orderBy('name')->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if (! $tenant instanceof Team || $this->isSuspended()) {
            return false;
        }

        return $this->isSystemAdmin() || $this->hasOversight() || $this->manages($tenant);
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

    public function qualifications(): HasMany
    {
        return $this->hasMany(Qualification::class);
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

    public function isCurrentMemberOf(Team $team): bool
    {
        if ($this->isSuspended()) {
            return false;
        }

        return $this->teams()->whereKey($team->getKey())->exists();
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
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

    public function sendAccountSetupNotification(): void
    {
        $this->notify(new SetupAccount);
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
