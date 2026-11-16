<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Models\Booking;
use App\Models\RotaSnapshot;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RotaDigest
{
    /**
     * @return array{changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}
     */
    public function delta(User $user): array
    {
        $current = $this->current($user);
        $previous = $this->persistSnapshot($user, $current);
        $allowed = MailPreference::allows($user, MailChannel::BookingsDigest);

        if (! $allowed || $previous === null) {
            return $this->empty();
        }

        return $this->diffLabels($current, $previous);
    }

    /**
     * @return Collection<string, array{team: Team, changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}>
     */
    public function deltasByTeam(User $user): Collection
    {
        $current = $this->current($user);
        $previous = $this->persistSnapshot($user, $current);
        $allowed = MailPreference::allows($user, MailChannel::BookingsDigest);

        if (! $allowed || $previous === null) {
            return collect();
        }

        $previousEntries = $this->normalizedEntries($previous);
        $teamIds = collect($current)->pluck('team_id')
            ->merge(collect($previousEntries)->pluck('team_id'))
            ->filter()
            ->unique()
            ->values();

        if ($teamIds->isEmpty()) {
            return collect();
        }

        /** @var Collection<string, Team> $teams */
        $teams = Team::query()->whereKey($teamIds->all())->orderBy('name')->get()->keyBy(
            fn (Team $team): string => (string) $team->getKey(),
        );

        $result = collect();

        foreach ($teams as $teamId => $team) {
            $diff = $this->diffLabelsForTeam($current, $previousEntries, $teamId);

            if (! $diff['changed']) {
                continue;
            }

            $result->put($teamId, [
                'team' => $team,
                'changed' => true,
                'additions' => $diff['additions'],
                'removals' => $diff['removals'],
                'remaining' => $diff['remaining'],
            ]);
        }

        return $result;
    }

    /**
     * @return Collection<int, string>
     */
    public function candidateUserIds(): Collection
    {
        return RotaSnapshot::query()->pluck('user_id')
            ->merge(
                DB::table('booking_user')
                    ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
                    ->where('bookings.end_at', '>', now())
                    ->where('booking_user.status', BookingAttendeeStatus::Accepted->value)
                    ->pluck('booking_user.user_id'),
            )
            ->unique()
            ->values();
    }

    /**
     * @return array<string, array{label: string, team_id: string|null}>
     */
    private function current(User $user): array
    {
        return Booking::query()
            ->where('end_at', '>', now())
            ->whereHas('attendees', function ($query) use ($user): void {
                $query->whereKey($user->getKey())
                    ->where('booking_user.status', BookingAttendeeStatus::Accepted->value);
            })
            ->with('activity')
            ->orderBy('start_at')
            ->get()
            ->mapWithKeys(fn (Booking $booking): array => [
                (string) $booking->getKey() => [
                    'label' => trim($booking->activityTitle().' '.$booking->start_at?->timezone(config('app.timezone'))->format('j M Y, H:i')),
                    'team_id' => filled($booking->team_id) ? (string) $booking->team_id : null,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, array{label: string, team_id: string|null}>  $current
     * @return array<string, array{label: string, team_id: string|null}>|null
     */
    private function persistSnapshot(User $user, array $current): ?array
    {
        $snapshot = RotaSnapshot::query()->find($user->getKey());
        $previous = is_array($snapshot?->entries) ? $snapshot->entries : null;

        RotaSnapshot::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['entries' => $current],
        );

        return $previous;
    }

    /**
     * @param  array<string, mixed>  $previous
     * @return array<string, array{label: string, team_id: string|null}>
     */
    private function normalizedEntries(array $previous): array
    {
        $legacyIds = [];

        foreach ($previous as $bookingId => $entry) {
            if (is_string($entry)) {
                $legacyIds[] = (string) $bookingId;
            }
        }

        $legacyTeamIds = $legacyIds === []
            ? []
            : Booking::query()->whereKey($legacyIds)->pluck('team_id', 'id')->all();

        $normalized = [];

        foreach ($previous as $bookingId => $entry) {
            $id = (string) $bookingId;

            if (is_string($entry)) {
                $teamId = $legacyTeamIds[$id] ?? null;
                $normalized[$id] = [
                    'label' => $entry,
                    'team_id' => filled($teamId) ? (string) $teamId : null,
                ];

                continue;
            }

            if (! is_array($entry)) {
                continue;
            }

            $normalized[$id] = [
                'label' => (string) ($entry['label'] ?? ''),
                'team_id' => filled($entry['team_id'] ?? null) ? (string) $entry['team_id'] : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{label: string, team_id: string|null}>  $current
     * @param  array<string, mixed>  $previous
     * @return array{changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}
     */
    private function diffLabels(array $current, array $previous): array
    {
        $previousEntries = $this->normalizedEntries($previous);

        $additions = [];
        $remaining = [];

        foreach ($current as $id => $entry) {
            if (! array_key_exists($id, $previousEntries)) {
                $additions[] = $entry['label'];
            } else {
                $remaining[] = $entry['label'];
            }
        }

        $removals = [];

        foreach ($previousEntries as $id => $entry) {
            if (! array_key_exists($id, $current)) {
                $removals[] = $entry['label'];
            }
        }

        return [
            'changed' => $additions !== [] || $removals !== [],
            'additions' => $additions,
            'removals' => $removals,
            'remaining' => $remaining,
        ];
    }

    /**
     * @param  array<string, array{label: string, team_id: string|null}>  $current
     * @param  array<string, array{label: string, team_id: string|null}>  $previousEntries
     * @return array{changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}
     */
    private function diffLabelsForTeam(array $current, array $previousEntries, string $teamId): array
    {
        $additions = [];
        $remaining = [];

        foreach ($current as $id => $entry) {
            if (($entry['team_id'] ?? null) !== $teamId) {
                continue;
            }

            if (! array_key_exists($id, $previousEntries) || ($previousEntries[$id]['team_id'] ?? null) !== $teamId) {
                $additions[] = $entry['label'];
            } else {
                $remaining[] = $entry['label'];
            }
        }

        $removals = [];

        foreach ($previousEntries as $id => $entry) {
            if (($entry['team_id'] ?? null) !== $teamId) {
                continue;
            }

            if (! array_key_exists($id, $current) || ($current[$id]['team_id'] ?? null) !== $teamId) {
                $removals[] = $entry['label'];
            }
        }

        return [
            'changed' => $additions !== [] || $removals !== [],
            'additions' => $additions,
            'removals' => $removals,
            'remaining' => $remaining,
        ];
    }

    /**
     * @return array{changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}
     */
    private function empty(): array
    {
        return [
            'changed' => false,
            'additions' => [],
            'removals' => [],
            'remaining' => [],
        ];
    }
}
