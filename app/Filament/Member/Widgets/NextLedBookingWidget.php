<?php

namespace App\Filament\Member\Widgets;

use App\Models\Booking;
use App\Support\LeadAttendanceWarning;

class NextLedBookingWidget extends DashboardSection
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.member.widgets.next-led-booking';

    protected function sectionData(): array
    {
        $rows = $this->dashboard()->nextLedDay();

        return [
            'rows' => $rows,
            'warnings' => $rows->mapWithKeys(fn (Booking $booking): array => [
                $booking->getKey() => $booking->attendees->first()?->attendance?->isAccepted()
                    ? null
                    : LeadAttendanceWarning::message($booking),
            ])->all(),
        ];
    }
}
