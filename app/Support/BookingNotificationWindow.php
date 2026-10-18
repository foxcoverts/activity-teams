<?php

namespace App\Support;

use App\Jobs\SendBookingNotification;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class BookingNotificationWindow
{
    public const UPDATED = 'updated';

    public const CONFIRMED = 'confirmed';

    public const CANCELLED = 'cancelled';

    public const INVITE = 'invite';

    /**
     * @param  list<string>  $changes
     * @param  array{start_at: mixed, end_at: mixed, timezone: mixed}|null  $previousSchedule
     * @param  list<string>|null  $recipientUserIds
     */
    public static function push(Booking $booking, string $kind, ?string $cancelReason = null, ?string $inviteUserId = null, array $changes = [], ?array $previousSchedule = null, ?array $recipientUserIds = null): void
    {
        $token = Cache::lock(self::lockKey($booking), 10)->block(5, function () use ($booking, $kind, $cancelReason, $inviteUserId, $changes, $previousSchedule, $recipientUserIds): string {
            $pending = Cache::get(self::cacheKey($booking), [
                'token' => null,
                'kinds' => [],
                'cancel_reason' => null,
                'invite_user_ids' => [],
                'changes' => [],
                'previous_schedule' => null,
            ]);

            $pending['token'] = (string) Str::uuid();
            $pending['kinds'][] = $kind;
            $pending['changes'] = array_values(array_unique(array_merge(
                $pending['changes'] ?? [],
                array_values(array_diff($changes, ['updated_at', 'sequence'])),
            )));

            if (is_array($previousSchedule)) {
                $pending['previous_schedule'] = array_merge(
                    $previousSchedule,
                    $pending['previous_schedule'] ?? [],
                );
            }

            if ($cancelReason !== null) {
                $pending['cancel_reason'] = $cancelReason;
            }

            if ($inviteUserId !== null) {
                $pending['invite_user_ids'][] = $inviteUserId;
            }

            if (is_array($recipientUserIds)) {
                $pending['recipient_user_ids'] = array_values(array_unique($recipientUserIds));
            }

            Cache::put(self::cacheKey($booking), $pending, now()->addMinutes(10));

            return $pending['token'];
        });

        SendBookingNotification::dispatch($booking, $token)
            ->delay(now()->addMinute());
    }

    /**
     * @return array{token: string, kinds: array<int, string>, cancel_reason: ?string, invite_user_ids: array<int, string>, changes: array<int, string>, previous_schedule: ?array, recipient_user_ids?: array<int, string>}|null
     */
    public static function claim(Booking $booking, string $token): ?array
    {
        return Cache::lock(self::lockKey($booking), 10)->block(5, function () use ($booking, $token): ?array {
            $pending = Cache::get(self::cacheKey($booking));

            if (! is_array($pending) || ($pending['token'] ?? null) !== $token) {
                return null;
            }

            Cache::forget(self::cacheKey($booking));

            return $pending;
        });
    }

    public static function cacheKey(Booking $booking): string
    {
        return 'booking-notification:'.$booking->getKey();
    }

    public static function lockKey(Booking $booking): string
    {
        return 'booking-notification-lock:'.$booking->getKey();
    }
}
