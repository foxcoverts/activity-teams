<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMembership::class);
    }

    public function currentMemberships(): HasMany
    {
        return $this->memberships()->whereNull('left_at');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot(['id', 'role', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at')
            ->using(TeamMembership::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->members()->wherePivot('role', MembershipRole::Manager->value);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class)->withTimestamps();
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function currentManagerCount(): int
    {
        return $this->managers()->count();
    }

    public function grant(User $user, MembershipRole $role): TeamMembership
    {
        $membership = $this->memberships()->firstOrNew([
            'user_id' => $user->getKey(),
        ]);

        if ($membership->exists && $membership->left_at === null && $membership->role === MembershipRole::Manager && $role !== MembershipRole::Manager) {
            $this->guardLastManager($user);
        }

        $membership->role = $role;
        $membership->left_at = null;
        $membership->save();

        return $membership;
    }

    public function recordLeft(User $user): void
    {
        $membership = $this->memberships()
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->first();

        if ($membership === null) {
            return;
        }

        if ($membership->role === MembershipRole::Manager) {
            $this->guardLastManager($user);
        }

        $membership->left_at = now();
        $membership->save();
    }

    public function isOnlyManager(User $user): bool
    {
        return $this->managers()
            ->where('users.id', $user->getKey())
            ->exists()
            && $this->currentManagerCount() <= 1;
    }

    public static function lastManagerMessage(): string
    {
        return __('Appoint another manager before this one steps down.');
    }

    public function guardLastManager(User $user): void
    {
        if ($this->isOnlyManager($user)) {
            throw ValidationException::withMessages([
                'role' => static::lastManagerMessage(),
            ]);
        }
    }
}
