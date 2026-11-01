<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function booted(): void
    {
        static::saving(function (Team $team): void {
            if (blank($team->slug) && filled($team->name)) {
                $team->slug = Str::slug($team->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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

    public function activeManagers(): BelongsToMany
    {
        return $this->managers()->where('users.suspended', false);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class)
            ->withPivot('default_audience_id')
            ->withTimestamps();
    }

    public function defaultActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'default_activity_id');
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class)->withTimestamps();
    }

    public function defaultVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'default_venue_id');
    }

    public function addActivity(Activity $activity): void
    {
        $this->addDefaultableRecord($activity, 'activities', 'default_activity_id');
    }

    public function addVenue(Venue $venue): void
    {
        $this->addDefaultableRecord($venue, 'venues', 'default_venue_id');
    }

    public function removeActivity(Activity $activity): void
    {
        $this->removeDefaultableRecord($activity, 'activities', 'default_activity_id');
    }

    public function removeVenue(Venue $venue): void
    {
        $this->removeDefaultableRecord($venue, 'venues', 'default_venue_id');
    }

    public function setDefaultActivity(Activity $activity): void
    {
        $this->setDefaultableRecord($activity, 'activities', 'default_activity_id');
    }

    public function setDefaultVenue(Venue $venue): void
    {
        $this->setDefaultableRecord($venue, 'venues', 'default_venue_id');
    }

    public function defaultAudienceFor(Activity $activity): ?Audience
    {
        $link = $this->activities()->whereKey($activity->getKey())->first();
        $audienceId = $link?->pivot?->default_audience_id;

        if ($audienceId === null) {
            return null;
        }

        $offered = ActivityAudience::query()
            ->where('activity_id', $activity->getKey())
            ->where('audience_id', $audienceId)
            ->exists();

        if (! $offered) {
            $this->activities()->updateExistingPivot($activity->getKey(), [
                'default_audience_id' => null,
            ]);

            return null;
        }

        return Audience::query()->find($audienceId);
    }

    public function setDefaultAudience(Activity $activity, ?Audience $audience): void
    {
        $this->activities()->whereKey($activity->getKey())->firstOrFail();

        if ($audience instanceof Audience) {
            $offered = ActivityAudience::query()
                ->where('activity_id', $activity->getKey())
                ->where('audience_id', $audience->getKey())
                ->exists();

            if (! $offered) {
                throw ValidationException::withMessages([
                    'default_audience_id' => __('Choose an audience this activity offers.'),
                ]);
            }
        }

        $this->activities()->updateExistingPivot($activity->getKey(), [
            'default_audience_id' => $audience?->getKey(),
        ]);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class);
    }

    private function addDefaultableRecord(Activity|Venue $record, string $relation, string $defaultAttribute): void
    {
        $defaultId = DB::transaction(function () use ($record, $relation, $defaultAttribute): string {
            $team = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $team->{$relation}()->syncWithoutDetaching([$record->getKey()]);

            if ($team->{$defaultAttribute} === null) {
                $team->{$defaultAttribute} = $record->getKey();
                $team->save();
            }

            return (string) $team->{$defaultAttribute};
        });

        $this->setAttribute($defaultAttribute, $defaultId);
    }

    private function removeDefaultableRecord(Activity|Venue $record, string $relation, string $defaultAttribute): void
    {
        $defaultId = DB::transaction(function () use ($record, $relation, $defaultAttribute): ?string {
            $team = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $team->{$relation}()->detach($record->getKey());

            if ($team->{$defaultAttribute} === $record->getKey()) {
                $table = $record->getTable();
                $team->{$defaultAttribute} = $team->{$relation}()
                    ->orderBy("{$table}.name")
                    ->orderBy("{$table}.id")
                    ->value("{$table}.id");
                $team->save();
            }

            return $team->{$defaultAttribute};
        });

        $this->setAttribute($defaultAttribute, $defaultId);
    }

    private function setDefaultableRecord(Activity|Venue $record, string $relation, string $defaultAttribute): void
    {
        DB::transaction(function () use ($record, $relation, $defaultAttribute): void {
            $team = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $team->{$relation}()->whereKey($record->getKey())->firstOrFail();
            $team->{$defaultAttribute} = $record->getKey();
            $team->save();
        });

        $this->setAttribute($defaultAttribute, $record->getKey());
    }

    public function currentManagerCount(): int
    {
        return $this->managers()->count();
    }

    public function grant(User $user, MembershipRole $role): TeamMembership
    {
        if ($user->trashed()) {
            throw ValidationException::withMessages([
                'role' => User::leftRecordMessage(),
            ]);
        }

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
        if ($user->trashed()) {
            throw ValidationException::withMessages([
                'role' => User::leftRecordMessage(),
            ]);
        }

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
