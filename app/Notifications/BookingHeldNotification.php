<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingHeldNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Booking $booking, public string $body)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return $this->body;
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'member');
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::BookingsMail;
    }

    public function inboxInAppOnly(): bool
    {
        return true;
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
            'body' => $this->body,
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
            (string) $rebuild['body'],
        );
    }
}
