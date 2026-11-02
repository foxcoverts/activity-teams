<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Models\Booking;
use App\Models\RotaSnapshot;
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
        $snapshot = RotaSnapshot::query()->find($user->getKey());
        $previous = is_array($snapshot?->entries) ? $snapshot->entries : null;
        $allowed = MailPreference::allows($user, MailChannel::BookingsDigest);

        RotaSnapshot::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['entries' => $current],
        );

        if (! $allowed || $previous === null) {
            return $this->empty();
        }

        $additions = [];
        $remaining = [];

        foreach ($current as $id => $label) {
            if (! array_key_exists($id, $previous)) {
                $additions[] = $label;
            } else {
                $remaining[] = $label;
            }
        }

        $removals = [];

        foreach ($previous as $id => $label) {
            if (! array_key_exists($id, $current)) {
                $removals[] = $label;
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
     * @return array<string, string>
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
                (string) $booking->getKey() => trim($booking->activityTitle().' '.$booking->start_at?->timezone(config('app.timezone'))->format('j M Y, H:i')),
            ])
            ->all();
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
