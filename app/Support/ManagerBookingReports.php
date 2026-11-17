<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManagerBookingReports
{
    public const PAST_TENTATIVE_PREVIEW_LIMIT = 5;

    public const TREND_YEARS = 5;

    /**
     * @return list<int>
     */
    public function availableYears(Team $team): array
    {
        $current = (int) now()->year;
        $earliest = Booking::query()
            ->where('team_id', $team->getKey())
            ->whereNotNull('start_at')
            ->min(DB::raw('YEAR(start_at)'));

        $from = is_numeric($earliest) ? (int) $earliest : $current;

        if ($from > $current) {
            $from = $current;
        }

        return range($current, $from);
    }

    /**
     * @return array{
     *     year: int,
     *     previous_year: int,
     *     confirmed: array{current: int, previous: int, absolute: int, percent: ?float},
     *     cancelled: array{current: int, previous: int, absolute: int, percent: ?float},
     *     cancellation_rate: array{current: ?float, previous: ?float, absolute: ?float, percent: ?float},
     *     hours: array{current: float, previous: float, absolute: float, percent: ?float},
     *     participants: array{current: int, previous: int, absolute: int, percent: ?float},
     *     debrief_coverage: array{debriefed: int, confirmed: int},
     *     past_tentative: array{current: int, previous: int, absolute: int, percent: ?float, live: int},
     *     response_rate: array{current: ?float, previous: ?float, absolute: ?float, percent: ?float},
     *     can_help_rate: array{current: ?float, previous: ?float, absolute: ?float, percent: ?float}
     * }
     */
    public function summary(Team $team, int $year): array
    {
        $previousYear = $year - 1;
        $currentVolume = $this->volumeTotals($team, $year);
        $previousVolume = $this->volumeTotals($team, $previousYear);
        $currentEngagement = $this->teamEngagementRates($team, $year);
        $previousEngagement = $this->teamEngagementRates($team, $previousYear);

        return [
            'year' => $year,
            'previous_year' => $previousYear,
            'confirmed' => $this->compareInt($currentVolume['confirmed'], $previousVolume['confirmed']),
            'cancelled' => $this->compareInt($currentVolume['cancelled'], $previousVolume['cancelled']),
            'cancellation_rate' => $this->compareNullableFloat(
                $this->cancellationRate($currentVolume['confirmed'], $currentVolume['cancelled']),
                $this->cancellationRate($previousVolume['confirmed'], $previousVolume['cancelled']),
            ),
            'hours' => $this->compareFloat($currentVolume['hours'], $previousVolume['hours']),
            'participants' => $this->compareInt($currentVolume['participants'], $previousVolume['participants']),
            'debrief_coverage' => [
                'debriefed' => $currentVolume['debriefed'],
                'confirmed' => $currentVolume['confirmed'],
            ],
            'past_tentative' => [
                ...$this->compareInt(
                    $this->pastTentativeCountForYear($team, $year),
                    $this->pastTentativeCountForYear($team, $previousYear),
                ),
                'live' => $this->pastTentativeLiveCount($team),
            ],
            'response_rate' => $this->compareNullableFloat($currentEngagement['response_rate'], $previousEngagement['response_rate']),
            'can_help_rate' => $this->compareNullableFloat($currentEngagement['can_help_rate'], $previousEngagement['can_help_rate']),
        ];
    }

    /**
     * @return Collection<int, array{key: string|null, label: string, confirmed: array{current: int, previous: int, absolute: int, percent: ?float}, cancelled: array{current: int, previous: int, absolute: int, percent: ?float}}>
     */
    public function breakdownByActivity(Team $team, int $year): Collection
    {
        return $this->breakdown($team, $year, 'activity');
    }

    /**
     * @return Collection<int, array{key: string|null, label: string, confirmed: array{current: int, previous: int, absolute: int, percent: ?float}, cancelled: array{current: int, previous: int, absolute: int, percent: ?float}, participants: array{current: int, previous: int, absolute: int, percent: ?float}}>
     */
    public function breakdownByAudience(Team $team, int $year): Collection
    {
        return $this->breakdown($team, $year, 'audience', withParticipants: true);
    }

    /**
     * @return Collection<int, array{key: string|null, label: string, confirmed: array{current: int, previous: int, absolute: int, percent: ?float}, cancelled: array{current: int, previous: int, absolute: int, percent: ?float}}>
     */
    public function breakdownByVenue(Team $team, int $year): Collection
    {
        return $this->breakdown($team, $year, 'venue');
    }

    /**
     * @return list<array{year: int, confirmed: int, cancelled: int}>
     */
    public function yearTrend(Team $team, int $throughYear, int $years = self::TREND_YEARS): array
    {
        $from = $throughYear - ($years - 1);
        $rows = [];

        for ($year = $from; $year <= $throughYear; $year++) {
            $totals = $this->volumeTotals($team, $year);

            if ($totals['confirmed'] === 0 && $totals['cancelled'] === 0) {
                continue;
            }

            $rows[] = [
                'year' => $year,
                'confirmed' => $totals['confirmed'],
                'cancelled' => $totals['cancelled'],
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function pastTentativeBookings(Team $team, int $year, int $limit = self::PAST_TENTATIVE_PREVIEW_LIMIT): Collection
    {
        return $this->pastTentativeQuery($team)
            ->tap(fn (Builder $query) => $this->constrainToYear($query, $year))
            ->with(['activity', 'audience', 'venue'])
            ->orderByDesc('start_at')
            ->limit($limit)
            ->get();
    }

    public function pastTentativeLiveCount(Team $team): int
    {
        return $this->pastTentativeQuery($team)->count();
    }

    public function pastTentativeCountForYear(Team $team, int $year): int
    {
        return $this->pastTentativeQuery($team)
            ->tap(fn (Builder $query) => $this->constrainToYear($query, $year))
            ->count();
    }

    /**
     * @return Collection<int, array{
     *     user: User,
     *     invites: int,
     *     responded: int,
     *     declined: int,
     *     tentative: int,
     *     accepted: int,
     *     awaiting: int,
     *     declined_share: float,
     *     tentative_share: float,
     *     accepted_share: float,
     *     awaiting_share: float,
     *     response_rate: ?float,
     *     can_help_rate: ?float,
     *     rollcall_eligible: int,
     *     rollcall_attended: int,
     *     rollcall_rate: ?float,
     *     leads: int,
     *     never_responded: bool
     * }>
     */
    public function memberEngagement(Team $team, int $year): Collection
    {
        $members = $team->members()->ordered()->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $memberIds = $members->modelKeys();
        [$start, $end] = $this->yearBounds($year);

        $inviteStats = DB::table('booking_user')
            ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
            ->where('bookings.team_id', $team->getKey())
            ->whereBetween('bookings.start_at', [$start, $end])
            ->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->whereIn('booking_user.user_id', $memberIds)
            ->groupBy('booking_user.user_id')
            ->selectRaw(
                'booking_user.user_id as user_id,
                 COUNT(*) as invites,
                 SUM(CASE WHEN booking_user.status = ? THEN 1 ELSE 0 END) as declined,
                 SUM(CASE WHEN booking_user.status = ? THEN 1 ELSE 0 END) as tentative,
                 SUM(CASE WHEN booking_user.status = ? THEN 1 ELSE 0 END) as accepted,
                 SUM(CASE WHEN booking_user.status = ? THEN 1 ELSE 0 END) as awaiting',
                [
                    BookingAttendeeStatus::Declined->value,
                    BookingAttendeeStatus::Tentative->value,
                    BookingAttendeeStatus::Accepted->value,
                    BookingAttendeeStatus::NeedsAction->value,
                ],
            )
            ->get()
            ->keyBy('user_id');

        $rollcallBookingIds = Booking::query()
            ->where('team_id', $team->getKey())
            ->whereBetween('start_at', [$start, $end])
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->past()
            ->whereHas('attendees', fn (Builder $query) => $query->whereNotNull('booking_user.attendance_confirmed_at'))
            ->pluck('id');

        $rollcallStats = collect();

        if ($rollcallBookingIds->isNotEmpty()) {
            $rollcallStats = DB::table('booking_user')
                ->whereIn('booking_id', $rollcallBookingIds)
                ->whereIn('user_id', $memberIds)
                ->groupBy('user_id')
                ->select([
                    'user_id',
                    DB::raw('COUNT(*) as rollcall_eligible'),
                    DB::raw('SUM(CASE WHEN attendance_confirmed_at IS NOT NULL THEN 1 ELSE 0 END) as rollcall_attended'),
                ])
                ->get()
                ->keyBy('user_id');
        }

        $leadStats = Booking::query()
            ->where('team_id', $team->getKey())
            ->whereBetween('start_at', [$start, $end])
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->whereIn('lead_instructor_id', $memberIds)
            ->selectRaw('lead_instructor_id as user_id, COUNT(*) as leads')
            ->groupBy('lead_instructor_id')
            ->pluck('leads', 'user_id');

        return $members
            ->map(function (User $member) use ($inviteStats, $rollcallStats, $leadStats): array {
                $invites = (int) ($inviteStats[$member->getKey()]->invites ?? 0);
                $declined = (int) ($inviteStats[$member->getKey()]->declined ?? 0);
                $tentative = (int) ($inviteStats[$member->getKey()]->tentative ?? 0);
                $accepted = (int) ($inviteStats[$member->getKey()]->accepted ?? 0);
                $awaiting = (int) ($inviteStats[$member->getKey()]->awaiting ?? 0);
                $responded = $declined + $tentative + $accepted;
                $rollcallEligible = (int) ($rollcallStats[$member->getKey()]->rollcall_eligible ?? 0);
                $rollcallAttended = (int) ($rollcallStats[$member->getKey()]->rollcall_attended ?? 0);
                $leads = (int) ($leadStats[$member->getKey()] ?? 0);

                return [
                    'user' => $member,
                    'invites' => $invites,
                    'responded' => $responded,
                    'declined' => $declined,
                    'tentative' => $tentative,
                    'accepted' => $accepted,
                    'awaiting' => $awaiting,
                    'declined_share' => $this->share($declined, $invites),
                    'tentative_share' => $this->share($tentative, $invites),
                    'accepted_share' => $this->share($accepted, $invites),
                    'awaiting_share' => $this->share($awaiting, $invites),
                    'response_rate' => $this->rate($responded, $invites),
                    'can_help_rate' => $this->rate($accepted, $invites),
                    'rollcall_eligible' => $rollcallEligible,
                    'rollcall_attended' => $rollcallAttended,
                    'rollcall_rate' => $this->rate($rollcallAttended, $rollcallEligible),
                    'leads' => $leads,
                    'never_responded' => $invites > 0 && $responded === 0,
                ];
            })
            ->sort(function (array $left, array $right): int {
                return [$right['accepted'], $right['responded'], $right['invites'], $left['user']->name]
                    <=> [$left['accepted'], $left['responded'], $left['invites'], $right['user']->name];
            })
            ->values();
    }

    /**
     * @return array{confirmed: int, cancelled: int, hours: float, participants: int, debriefed: int}
     */
    private function volumeTotals(Team $team, int $year): array
    {
        $confirmed = BookingStatus::Confirmed->value;
        $cancelled = BookingStatus::Cancelled->value;

        $row = Booking::query()
            ->from('bookings')
            ->where('bookings.team_id', $team->getKey())
            ->tap(fn (Builder $query) => $this->constrainToYear($query, $year))
            ->leftJoin('booking_debriefs', 'booking_debriefs.booking_id', '=', 'bookings.id')
            ->selectRaw(
                'SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as confirmed_count,
                 SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as cancelled_count,
                 SUM(CASE WHEN bookings.status = ? AND booking_debriefs.id IS NOT NULL THEN 1 ELSE 0 END) as debriefed_count,
                 SUM(CASE WHEN bookings.status = ? AND booking_debriefs.id IS NOT NULL
                     THEN booking_debriefs.participant_count ELSE 0 END) as participants,
                 SUM(CASE WHEN bookings.status = ? THEN
                     COALESCE(
                         booking_debriefs.hours,
                         CASE WHEN bookings.start_at IS NOT NULL AND bookings.end_at IS NOT NULL AND bookings.end_at > bookings.start_at
                             THEN TIMESTAMPDIFF(SECOND, bookings.start_at, bookings.end_at) / 3600.0
                             ELSE 0 END
                     )
                     ELSE 0 END) as hours',
                [
                    $confirmed,
                    $cancelled,
                    $confirmed,
                    $confirmed,
                    $confirmed,
                ],
            )
            ->first();

        return [
            'confirmed' => (int) ($row->confirmed_count ?? 0),
            'cancelled' => (int) ($row->cancelled_count ?? 0),
            'hours' => round((float) ($row->hours ?? 0), 2),
            'participants' => (int) ($row->participants ?? 0),
            'debriefed' => (int) ($row->debriefed_count ?? 0),
        ];
    }

    /**
     * @return array{response_rate: ?float, can_help_rate: ?float}
     */
    private function teamEngagementRates(Team $team, int $year): array
    {
        [$start, $end] = $this->yearBounds($year);

        $row = DB::table('booking_user')
            ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
            ->where('bookings.team_id', $team->getKey())
            ->whereBetween('bookings.start_at', [$start, $end])
            ->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->selectRaw(
                'COUNT(*) as invites,
                 SUM(CASE WHEN booking_user.status != ? THEN 1 ELSE 0 END) as responded,
                 SUM(CASE WHEN booking_user.status = ? THEN 1 ELSE 0 END) as accepted',
                [
                    BookingAttendeeStatus::NeedsAction->value,
                    BookingAttendeeStatus::Accepted->value,
                ],
            )
            ->first();

        $invites = (int) ($row->invites ?? 0);
        $responded = (int) ($row->responded ?? 0);
        $accepted = (int) ($row->accepted ?? 0);

        return [
            'response_rate' => $this->rate($responded, $invites),
            'can_help_rate' => $this->rate($accepted, $invites),
        ];
    }

    /**
     * @param  'activity'|'audience'|'venue'  $dimension
     * @return Collection<int, array{key: string|null, label: string, confirmed: array{current: int, previous: int, absolute: int, percent: ?float}, cancelled: array{current: int, previous: int, absolute: int, percent: ?float}, participants?: array{current: int, previous: int, absolute: int, percent: ?float}}>
     */
    private function breakdown(Team $team, int $year, string $dimension, bool $withParticipants = false): Collection
    {
        $previousYear = $year - 1;
        $nullLabel = match ($dimension) {
            'activity' => __('Other activity'),
            'audience' => __('No audience'),
            default => __('No venue'),
        };
        $current = $this->breakdownCounts($team, $year, $dimension, $nullLabel, $withParticipants);
        $previous = $this->breakdownCounts($team, $previousYear, $dimension, $nullLabel, $withParticipants);

        $keys = $current->keys()->merge($previous->keys())->unique();

        return $keys
            ->map(function (mixed $key) use ($current, $previous, $nullLabel, $withParticipants): array {
                $defaults = ['label' => $nullLabel, 'confirmed' => 0, 'cancelled' => 0, 'participants' => 0];
                $currentRow = $current->get($key, $defaults);
                $previousRow = $previous->get($key, $defaults);

                $row = [
                    'key' => $key === '' ? null : (string) $key,
                    'label' => $currentRow['label'] !== $nullLabel ? $currentRow['label'] : $previousRow['label'],
                    'confirmed' => $this->compareInt((int) $currentRow['confirmed'], (int) $previousRow['confirmed']),
                    'cancelled' => $this->compareInt((int) $currentRow['cancelled'], (int) $previousRow['cancelled']),
                ];

                if ($withParticipants) {
                    $row['participants'] = $this->compareInt(
                        (int) $currentRow['participants'],
                        (int) $previousRow['participants'],
                    );
                }

                return $row;
            })
            ->sortByDesc(fn (array $row): int => $row['confirmed']['current'])
            ->values();
    }

    /**
     * @param  'activity'|'audience'|'venue'  $dimension
     * @return Collection<string, array{label: string, confirmed: int, cancelled: int, participants: int}>
     */
    private function breakdownCounts(
        Team $team,
        int $year,
        string $dimension,
        string $nullLabel,
        bool $withParticipants = false,
    ): Collection {
        [$foreignKey, $table, $labelExpression] = match ($dimension) {
            'activity' => [
                'activity_id',
                'activities',
                'COALESCE(MAX(activities.name), MAX(NULLIF(bookings.activity_label, "")), ?)',
            ],
            'audience' => [
                'audience_id',
                'audiences',
                'COALESCE(MAX(audiences.name), ?)',
            ],
            default => [
                'venue_id',
                'venues',
                'COALESCE(MAX(venues.name), ?)',
            ],
        };

        $confirmed = BookingStatus::Confirmed->value;
        $cancelled = BookingStatus::Cancelled->value;

        $query = Booking::query()
            ->from('bookings')
            ->where('bookings.team_id', $team->getKey())
            ->tap(fn (Builder $builder) => $this->constrainToYear($builder, $year))
            ->leftJoin($table, "{$table}.id", '=', "bookings.{$foreignKey}");

        if ($withParticipants) {
            $query->leftJoin('booking_debriefs', 'booking_debriefs.booking_id', '=', 'bookings.id');
        }

        $select = "bookings.{$foreignKey} as dimension_key,
                 {$labelExpression} as label,
                 SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as confirmed,
                 SUM(CASE WHEN bookings.status = ? THEN 1 ELSE 0 END) as cancelled";
        $bindings = [$nullLabel, $confirmed, $cancelled];

        if ($withParticipants) {
            $select .= ',
                 SUM(CASE WHEN bookings.status = ? AND booking_debriefs.id IS NOT NULL
                     THEN booking_debriefs.participant_count ELSE 0 END) as participants';
            $bindings[] = $confirmed;
        }

        return $query
            ->groupBy("bookings.{$foreignKey}")
            ->selectRaw($select, $bindings)
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (string) ($row->dimension_key ?? '') => [
                    'label' => (string) $row->label,
                    'confirmed' => (int) $row->confirmed,
                    'cancelled' => (int) $row->cancelled,
                    'participants' => (int) ($row->participants ?? 0),
                ],
            ]);
    }

    private function pastTentativeQuery(Team $team): Builder
    {
        return Booking::query()
            ->where('team_id', $team->getKey())
            ->where('status', BookingStatus::Tentative)
            ->past();
    }

    private function constrainToYear(Builder $query, int $year): void
    {
        [$start, $end] = $this->yearBounds($year);

        $query->overlapping($start, $end);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function yearBounds(int $year): array
    {
        $tz = (string) config('app.timezone');

        return [
            CarbonImmutable::create($year, 1, 1, 0, 0, 0, $tz),
            CarbonImmutable::create($year, 12, 31, 23, 59, 59, $tz),
        ];
    }

    private function cancellationRate(int $confirmed, int $cancelled): ?float
    {
        $decided = $confirmed + $cancelled;

        if ($decided === 0) {
            return null;
        }

        return round($cancelled / $decided, 4);
    }

    private function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round($numerator / $denominator, 4);
    }

    private function share(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    /**
     * @return array{current: int, previous: int, absolute: int, percent: ?float}
     */
    private function compareInt(int $current, int $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'absolute' => $current - $previous,
            'percent' => $this->percentChange((float) $current, (float) $previous),
        ];
    }

    /**
     * @return array{current: float, previous: float, absolute: float, percent: ?float}
     */
    private function compareFloat(float $current, float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'absolute' => round($current - $previous, 2),
            'percent' => $this->percentChange($current, $previous),
        ];
    }

    /**
     * @return array{current: ?float, previous: ?float, absolute: ?float, percent: ?float}
     */
    private function compareNullableFloat(?float $current, ?float $previous): array
    {
        return [
            'current' => $current,
            'previous' => $previous,
            'absolute' => $current !== null && $previous !== null
                ? round($current - $previous, 4)
                : null,
            'percent' => $current !== null && $previous !== null
                ? $this->percentChange($current, $previous)
                : null,
        ];
    }

    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
