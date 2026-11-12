<?php

namespace App\Support;

use App\Enums\AttentionSeverity;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\TentativeTriage;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class ManagerAttention
{
    public const PREVIEW_LIMIT = 5;

    public const AT_RISK_DAYS = 14;

    public const CACHE_TTL_SECONDS = 120;

    public function __construct(
        private Team $team,
        private ?User $viewer = null,
    ) {}

    public static function for(Team $team, ?User $viewer = null): self
    {
        return new self($team, $viewer ?? auth()->user());
    }

    public static function cacheKey(string $teamId): string
    {
        return "manager-attention:{$teamId}";
    }

    public static function flush(?string $teamId): void
    {
        if (filled($teamId)) {
            Cache::forget(self::cacheKey($teamId));
        }
    }

    public static function flushForUser(User $user): void
    {
        $user->loadMissing('teams');

        foreach ($user->teams as $team) {
            self::flush($team->getKey());
        }
    }

    /**
     * Booking triage is for team managers. System admins may open Manager for bounce triage
     * but BookingPolicy does not grant them booking access.
     */
    public function includesBookingTriage(): bool
    {
        return $this->viewer instanceof User && $this->viewer->manages($this->team);
    }

    /**
     * @return array{total: int, severity: AttentionSeverity|null}
     */
    public function summary(): array
    {
        if (! $this->includesBookingTriage()) {
            return $this->computeSummary(includeBookings: false);
        }

        return Cache::remember(
            self::cacheKey($this->team->getKey()),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->computeSummary(includeBookings: true),
        );
    }

    /**
     * @return array{total: int, severity: AttentionSeverity|null}
     */
    public function computeSummary(bool $includeBookings = true): array
    {
        $contributors = [];

        if ($includeBookings) {
            $contributors = [
                ...$contributors,
                ['count' => $this->needsLead()->count(), 'severity' => AttentionSeverity::Uncertain],
                ['count' => $this->atRisk()->count(), 'severity' => AttentionSeverity::Uncertain],
                ['count' => $this->unstaffed()->count(), 'severity' => AttentionSeverity::Uncertain],
            ];
        }

        $contributors[] = ['count' => $this->bouncedMembers()->count(), 'severity' => AttentionSeverity::Destructive];

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
     * @return Collection<int, Booking>
     */
    public function needsLead(): Collection
    {
        return $this->upcomingTentatives()
            ->filter(fn (Booking $booking): bool => $booking->lead_needed_at !== null)
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function readyToConfirm(): Collection
    {
        return $this->upcomingTentatives()
            ->filter(fn (Booking $booking): bool => $booking->lead_needed_at === null
                && $this->classify($booking) === TentativeTriage::ReadyToConfirm)
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function unstaffed(): Collection
    {
        return $this->upcomingTentatives()
            ->filter(fn (Booking $booking): bool => $booking->lead_needed_at === null
                && ! $this->canConfirm($booking)
                && ! $this->isWithinAtRiskWindow($booking))
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function atRisk(): Collection
    {
        return $this->upcomingTentatives()
            ->filter(fn (Booking $booking): bool => $booking->lead_needed_at === null
                && ! $this->canConfirm($booking)
                && $this->isWithinAtRiskWindow($booking))
            ->values();
    }

    /**
     * Unstaffed bookings including those in the at-risk window, for a combined section.
     *
     * @return Collection<int, Booking>
     */
    public function needsStaffing(): Collection
    {
        return $this->upcomingTentatives()
            ->filter(fn (Booking $booking): bool => $booking->lead_needed_at === null
                && ! $this->canConfirm($booking))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function bouncedMembers(): Collection
    {
        return $this->team->members()
            ->whereNotNull('email_bounced_at')
            ->ordered()
            ->get();
    }

    public function classify(Booking $booking): TentativeTriage
    {
        if ($booking->lead_needed_at !== null) {
            return TentativeTriage::NeedsLead;
        }

        if ($this->canConfirm($booking)) {
            return TentativeTriage::ReadyToConfirm;
        }

        if ($this->isWithinAtRiskWindow($booking)) {
            return TentativeTriage::AtRisk;
        }

        return TentativeTriage::Unstaffed;
    }

    public function canConfirm(Booking $booking): bool
    {
        if ($booking->requires_lead_instructor) {
            return $booking->leadOptions() !== [];
        }

        return $this->usefulAttendees($booking)->isNotEmpty();
    }

    /**
     * @return Collection<int, User>
     */
    public function usefulAttendees(Booking $booking): Collection
    {
        $accepted = $booking->acceptedAttendees();

        if (! $booking->requiresQualification()) {
            return $accepted;
        }

        return $accepted
            ->filter(fn (User $user): bool => $booking->metBy($user))
            ->values();
    }

    public function isWithinAtRiskWindow(Booking $booking): bool
    {
        $start = $booking->start_at;

        if (! $start instanceof CarbonInterface) {
            return false;
        }

        return $start->lessThanOrEqualTo(now()->addDays(self::AT_RISK_DAYS));
    }

    /**
     * @return Collection<int, Booking>
     */
    private function upcomingTentatives(): Collection
    {
        if (! $this->includesBookingTriage()) {
            return collect();
        }

        return Booking::query()
            ->where('team_id', $this->team->getKey())
            ->where('status', BookingStatus::Tentative)
            ->where('start_at', '>', now())
            ->with([
                'activity.activityAudiences.qualificationRequirements',
                'audience',
                'venue',
                'team',
                'attendees' => fn ($query) => $query->wherePivot('status', BookingAttendeeStatus::Accepted->value),
            ])
            ->orderBy('start_at')
            ->get();
    }
}
