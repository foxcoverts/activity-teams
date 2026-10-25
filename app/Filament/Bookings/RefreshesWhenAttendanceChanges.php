<?php

namespace App\Filament\Bookings;

use App\Models\Booking;
use Livewire\Attributes\On;

trait RefreshesWhenAttendanceChanges
{
    #[On('booking-attendance-updated')]
    public function refreshAfterAttendanceChange(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Booking) {
            $record->unsetRelation('attendees');
        }
    }
}
