<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Mail\AttendanceRecordedMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class AttendanceRecordedNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Booking $booking, public User $attendee)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        $attendance = $this->booking->attendees()->whereKey($this->attendee->getKey())->first()?->attendance;
        $status = $attendance?->status?->getLabel() ?? __('updated');
        $activity = $this->booking->activityTitle();

        return __('Your attendance for :activity was recorded as :status.', [
            'activity' => $activity,
            'status' => $status,
        ]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'member');
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::BookingsMail;
    }

    public function inboxSubject(): ?Booking
    {
        return $this->booking;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'booking_id' => $this->booking->getKey(),
            'attendee_id' => $this->attendee->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];

        return new self(
            Booking::query()->findOrFail($rebuild['booking_id']),
            User::query()->findOrFail($rebuild['attendee_id']),
        );
    }

    public function toMail(object $notifiable): Mailable
    {
        return new AttendanceRecordedMail($this->booking, $this->attendee);
    }
}
