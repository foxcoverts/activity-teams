<?php

namespace App\Models;

use App\Casts\AsTimezone;
use App\Casts\AsUniqueEnumCollection;
use App\Enums\Accreditation;
use App\Enums\Role;
use App\Enums\Section;
use App\Notifications\SetupAccount;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
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
        'role',
        'accreditations',
        'ical_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => Role::Guest->value,
        'section' => Section::Adult->value,
    ];

    protected function casts(): array
    {
        return [
            'accreditations' => AsUniqueEnumCollection::of(Accreditation::class),
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'section' => Section::class,
            'timezone' => AsTimezone::class,
        ];
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
            return $this->isTeamLeader() || $this->isBookingManager() || $this->isUserManager();
        }

        return true;
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class)
            ->withTimestamps()
            ->withPivot('comment', 'status', 'token')->as('attendance')
            ->using(BookingAttendance::class);
    }

    public function keys(): HasMany
    {
        return $this->hasMany(Key::class, 'holder_id')->orderBy('name');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }

    public function isActive(): bool
    {
        return filled($this->password);
    }

    public function isTeamLeader(): bool
    {
        return $this->role === Role::TeamLeader;
    }

    public function isGuest(): bool
    {
        return $this->role === Role::Guest;
    }

    public function isSuspended(): bool
    {
        return $this->role === Role::Suspended;
    }

    public function isBookingManager(): bool
    {
        return $this->accreditations->contains(Accreditation::ManageBookings);
    }

    public function isUserManager(): bool
    {
        return $this->accreditations->contains(Accreditation::ManageUsers);
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
