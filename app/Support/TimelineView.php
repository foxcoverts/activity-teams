<?php

namespace App\Support;

use App\Enums\TimelineAudience;
use App\Models\Booking;
use App\Models\Task;
use App\Models\User;

/**
 * Request-scoped audience for booking/task Recent activity rendering.
 * Set from the view page before the Timeline schema builds; read from TimelineProvider helpers.
 */
class TimelineView
{
    private static TimelineAudience $audience = TimelineAudience::Manager;

    private static ?User $viewer = null;

    private static ?Booking $booking = null;

    private static ?Task $task = null;

    public static function set(
        TimelineAudience $audience,
        ?User $viewer = null,
        ?Booking $booking = null,
        ?Task $task = null,
    ): void {
        self::$audience = $audience;
        self::$viewer = $viewer;
        self::$booking = $booking;
        self::$task = $task;
    }

    public static function audience(): TimelineAudience
    {
        return self::$audience;
    }

    public static function viewer(): ?User
    {
        return self::$viewer;
    }

    public static function booking(): ?Booking
    {
        return self::$booking;
    }

    public static function task(): ?Task
    {
        return self::$task;
    }

    public static function viewerIsLead(): bool
    {
        $viewer = self::$viewer;
        $booking = self::$booking;

        return $viewer instanceof User
            && $booking instanceof Booking
            && $booking->isLead($viewer);
    }
}
