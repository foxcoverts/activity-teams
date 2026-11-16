<?php

namespace App\Support;

use App\Enums\AttentionSeverity;
use App\Enums\InboundMailOutcome;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AdminAttention
{
    public const PREVIEW_LIMIT = 5;

    public const STALE_DAYS = 7;

    public function __construct(private User $viewer) {}

    public static function for(User $viewer): self
    {
        return new self($viewer);
    }

    public function includesInbound(): bool
    {
        return $this->viewer->isSystemAdmin();
    }

    public function includesCatalogTriage(): bool
    {
        return $this->viewer->isSystemAdmin();
    }

    public function includesDirectoryTriage(): bool
    {
        return $this->viewer->isSystemAdmin() || $this->viewer->hasOversight();
    }

    /**
     * @return array{total: int, severity: AttentionSeverity|null}
     */
    public function summary(): array
    {
        $contributors = [];

        if ($this->includesInbound()) {
            $inbound = $this->inboundNeedingReview();
            $contributors[] = [
                'count' => $inbound->count(),
                'severity' => $this->inboundSeverity($inbound),
            ];
        }

        if ($this->includesCatalogTriage()) {
            $contributors[] = [
                'count' => $this->catalogOrphanTotal(),
                'severity' => AttentionSeverity::Uncertain,
            ];
        }

        if ($this->includesDirectoryTriage()) {
            $contributors[] = [
                'count' => $this->pendingSiteInvites()->count(),
                'severity' => AttentionSeverity::Uncertain,
            ];
            $contributors[] = [
                'count' => $this->teamsWithoutManagers()->count(),
                'severity' => AttentionSeverity::Uncertain,
            ];
        }

        $total = 0;
        $severity = null;

        foreach ($contributors as $contributor) {
            if ($contributor['count'] < 1) {
                continue;
            }

            $total += $contributor['count'];

            if ($severity === null || $contributor['severity']->outranks($severity)) {
                $severity = $contributor['severity'];
            }
        }

        return [
            'total' => $total,
            'severity' => $severity,
        ];
    }

    /**
     * @return array{
     *     teams: int,
     *     activities: int,
     *     venues: int,
     *     audiences: int,
     *     keys: int,
     * }
     */
    public function snapshot(): array
    {
        return [
            'teams' => Team::query()->count(),
            'activities' => Activity::query()->count(),
            'venues' => Venue::query()->count(),
            'audiences' => Audience::query()->count(),
            'keys' => Key::query()->count(),
        ];
    }

    public function isCaughtUp(): bool
    {
        return $this->summary()['total'] === 0;
    }

    public function catalogOrphanTotal(): int
    {
        if (! $this->includesCatalogTriage()) {
            return 0;
        }

        return $this->activitiesWithoutTeams()->count()
            + $this->venuesWithoutTeams()->count()
            + $this->audiencesWithoutActivities()->count()
            + $this->keysWithoutLocks()->count();
    }

    /**
     * @return Collection<int, InboundMail>
     */
    public function inboundNeedingReview(): Collection
    {
        if (! $this->includesInbound()) {
            return collect();
        }

        return InboundMail::query()
            ->needsReview()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function activitiesWithoutTeams(): Collection
    {
        if (! $this->includesCatalogTriage()) {
            return collect();
        }

        return Activity::query()
            ->whereDoesntHave('teams')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Venue>
     */
    public function venuesWithoutTeams(): Collection
    {
        if (! $this->includesCatalogTriage()) {
            return collect();
        }

        return Venue::query()
            ->whereDoesntHave('teams')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Audience>
     */
    public function audiencesWithoutActivities(): Collection
    {
        if (! $this->includesCatalogTriage()) {
            return collect();
        }

        return Audience::query()
            ->whereDoesntHave('activityAudiences')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Key>
     */
    public function keysWithoutLocks(): Collection
    {
        if (! $this->includesCatalogTriage()) {
            return collect();
        }

        return Key::query()
            ->whereDoesntHave('locks')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function pendingSiteInvites(): Collection
    {
        if (! $this->includesDirectoryTriage()) {
            return collect();
        }

        return User::query()
            ->whereNull('users.email')
            ->whereNull('users.deleted_at')
            ->whereHas('invitation', function (Builder $query): void {
                $query->whereNull('team_id')
                    ->whereNull('revoked_at')
                    ->whereNull('declined_at');
            })
            ->with(['invitation'])
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, Team>
     */
    public function teamsWithoutManagers(): Collection
    {
        if (! $this->includesDirectoryTriage()) {
            return collect();
        }

        return Team::query()
            ->whereDoesntHave('activeManagers')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, InboundMail>  $inbound
     */
    private function inboundSeverity(Collection $inbound): AttentionSeverity
    {
        if ($inbound->isEmpty()) {
            return AttentionSeverity::Neutral;
        }

        $staleBefore = now()->subDays(self::STALE_DAYS);

        $hasFailureOrStale = $inbound->contains(function (InboundMail $mail) use ($staleBefore): bool {
            if ($mail->outcome === InboundMailOutcome::Failed) {
                return true;
            }

            return $mail->created_at !== null && $mail->created_at->lessThan($staleBefore);
        });

        return $hasFailureOrStale
            ? AttentionSeverity::Destructive
            : AttentionSeverity::Uncertain;
    }
}
