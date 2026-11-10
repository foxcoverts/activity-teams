<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Task;
use DateInterval;
use Eluceo\iCal\Presentation\Component;

class CombinedCalendarIcs
{
    public function __construct(
        private BookingIcsFactory $bookings,
        private TaskIcsFactory $tasks,
    ) {}

    /**
     * @param  iterable<int, Booking>  $bookingRecords
     * @param  iterable<int, Task>  $taskRecords
     */
    public function make(
        iterable $bookingRecords,
        iterable $taskRecords,
        string $title,
        ?DateInterval $publishedTtl = null,
    ): string {
        $bookingBody = $this->bookings->make(
            $bookingRecords,
            $title,
            publishedTtl: $publishedTtl,
        );

        $taskComponents = $this->tasks->components($taskRecords);

        if ($taskComponents === []) {
            return $bookingBody;
        }

        // Inject VTODO components before END:VCALENDAR.
        $todoBlock = implode('', array_map(
            static fn (Component $component): string => (string) $component,
            $taskComponents,
        ));

        return preg_replace(
            '/END:VCALENDAR\s*$/',
            $todoBlock.'END:VCALENDAR',
            rtrim($bookingBody),
            1,
        ) ?? ($bookingBody.$todoBlock);
    }

    /**
     * @param  iterable<int, Task>  $taskRecords
     */
    public function tasksOnly(
        iterable $taskRecords,
        string $title,
        ?DateInterval $publishedTtl = null,
    ): string {
        return $this->tasks->make($taskRecords, $title, $publishedTtl);
    }
}
