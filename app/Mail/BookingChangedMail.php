<?php

namespace App\Mail;

class BookingChangedMail extends BookingStatusMail
{
    protected function statusHeading(): string
    {
        return __('Booking updated');
    }

    protected function statusIntro(): string
    {
        return __('A booking you are invited to has changed.');
    }

    protected function changeNotice(): ?string
    {
        return __('This booking has been updated');
    }

    protected function announcesLeadNeeded(): bool
    {
        if ($this->booking->lead_instructor_id !== null) {
            return false;
        }

        if (! in_array('lead_instructor_id', $this->changes, true)) {
            return false;
        }

        return $this->attendee->managedTeams()->whereKey($this->booking->team_id)->exists();
    }
}
