<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\ScheduleNudgeTier;
use App\Enums\TentativeTriage;
use App\Models\Booking;
use App\Models\ManagerAttentionNotification;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ManagerAttentionCatchUp
{
    /**
     * @return array{new: Collection<int, array{booking: Booking, triage: TentativeTriage, tier: ScheduleNudgeTier, label: string, team: Team}>, outstanding: Collection<int, array{booking: Booking, triage: TentativeTriage, tier: ScheduleNudgeTier, label: string, team: Team}>}
     */
    public function due(User $user): array
    {
        $teams = $user->managedTeams()->orderBy('name')->get();

        if ($teams->isEmpty()) {
            return ['new' => collect(), 'outstanding' => collect()];
        }

        $stamps = ManagerAttentionNotification::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy(fn (ManagerAttentionNotification $row): string => (string) $row->booking_id);

        $new = collect();
        $outstanding = collect();
        $multiTeam = $teams->count() > 1;

        foreach ($teams as $team) {
            $attention = ManagerAttention::for($team, $user);

            foreach ($this->attentionBookings($attention) as $booking) {
                if (! $booking->start_at instanceof CarbonInterface) {
                    continue;
                }

                $currentBand = ScheduleNudgeTier::forStartAt($booking->start_at);

                if (! $currentBand instanceof ScheduleNudgeTier) {
                    continue;
                }

                $triage = $attention->classify($booking);
                $stamp = $stamps->get((string) $booking->getKey());
                [$sent, $clock] = $this->sentAndClock($booking, $triage, $stamp instanceof ManagerAttentionNotification ? $stamp : null);

                $dueTier = ScheduleNudgeTier::due($sent, $booking->start_at, $clock);

                if ($dueTier instanceof ScheduleNudgeTier) {
                    $new->push([
                        'booking' => $booking,
                        'triage' => $triage,
                        'tier' => $dueTier,
                        'label' => $this->label($booking, $triage, $team, $multiTeam),
                        'team' => $team,
                    ]);

                    continue;
                }

                if (! $stamp instanceof ManagerAttentionNotification || $stamp->triage !== $triage) {
                    continue;
                }

                $outstanding->push([
                    'booking' => $booking,
                    'triage' => $triage,
                    'tier' => $sent ?? $currentBand,
                    'label' => $this->label($booking, $triage, $team, $multiTeam),
                    'team' => $team,
                ]);
            }
        }

        return [
            'new' => $new->values(),
            'outstanding' => $outstanding->values(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function candidateUserIds(): Collection
    {
        return DB::table('team_user')
            ->join('bookings', 'bookings.team_id', '=', 'team_user.team_id')
            ->where('team_user.role', MembershipRole::Manager->value)
            ->whereNull('team_user.left_at')
            ->where('bookings.status', BookingStatus::Tentative->value)
            ->where('bookings.start_at', '>', now())
            ->where('bookings.start_at', '<=', now()->addDays(ScheduleNudgeTier::max()->days()))
            ->distinct()
            ->pluck('team_user.user_id');
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, tier: ScheduleNudgeTier, label: string, team: Team}>  $items
     */
    public function stamp(User $user, Collection $items): void
    {
        $now = now();

        foreach ($items as $item) {
            ManagerAttentionNotification::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'booking_id' => $item['booking']->getKey(),
                ],
                [
                    'triage' => $item['triage']->value,
                    'nudge_tier' => $item['tier']->value,
                    'notified_at' => $now,
                ],
            );
        }
    }

    /**
     * @return array{0: ?ScheduleNudgeTier, 1: CarbonInterface}
     */
    private function sentAndClock(Booking $booking, TentativeTriage $triage, ?ManagerAttentionNotification $stamp): array
    {
        $bookingClock = $booking->updated_at ?? $booking->created_at ?? now();

        if (! $stamp instanceof ManagerAttentionNotification) {
            return [null, $bookingClock];
        }

        if ($stamp->triage !== $triage) {
            return [null, $bookingClock];
        }

        $clock = $stamp->notified_at ?? $bookingClock;

        if ($stamp->nudge_tier === null) {
            return [ScheduleNudgeTier::forStartAt($booking->start_at), $clock];
        }

        return [$stamp->nudge_tier, $clock];
    }

    /**
     * @return Collection<int, Booking>
     */
    private function attentionBookings(ManagerAttention $attention): Collection
    {
        return $attention->needsLead()
            ->merge($attention->readyToConfirm())
            ->merge($attention->needsStaffing())
            ->unique(fn (Booking $booking): string => (string) $booking->getKey())
            ->values();
    }

    private function label(Booking $booking, TentativeTriage $triage, Team $team, bool $multiTeam): string
    {
        $booking->loadMissing('activity');

        $line = trim(
            $triage->getLabel()
            .' · '
            .$booking->activityTitle()
            .' '
            .$booking->start_at?->timezone(config('app.timezone'))->format('j M Y, H:i')
        );

        if ($multiTeam) {
            $line .= ' ('.$team->name.')';
        }

        return $line;
    }
}
