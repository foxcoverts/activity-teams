<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Mail\LeadNeededMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

class LeadNeededNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Booking $booking, public ?User $previousLead)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        $activity = $this->booking->activityTitle();

        if ($this->previousLead instanceof User) {
            return __(':name is no longer the lead instructor for :activity.', [
                'name' => $this->previousLead->name,
                'activity' => $activity,
            ]);
        }

        return __('Lead needed for :activity.', ['activity' => $activity]);
    }

    public function inboxUrl(object $notifiable): ?string
    {
        $team = $this->booking->team;

        if ($team === null) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'teams', tenant: $team);
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::BookingsUpdates;
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
            'previous_lead_id' => $this->previousLead?->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromInbox(array $data): self
    {
        $rebuild = $data['rebuild'];
        $booking = Booking::query()->findOrFail($rebuild['booking_id']);
        $previous = filled($rebuild['previous_lead_id'] ?? null)
            ? User::withTrashed()->find($rebuild['previous_lead_id'])
            : null;

        return new self($booking, $previous);
    }

    public function toMail(object $notifiable): Mailable
    {
        return new LeadNeededMail($this->booking, $this->previousLead);
    }
}
