<?php

namespace App\Mail;

use App\Enums\MailGroup;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\User;
use App\Support\BookingMailThread;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class BookingCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Comment $comment,
        public User $recipient,
    ) {
        $this->afterCommit();
        $this->comment->loadMissing(['author', 'commentable.activity', 'commentable.venue']);
    }

    public function envelope(): Envelope
    {
        $booking = $this->booking();

        return new Envelope(
            subject: BookingMailThread::subject($booking, __('Re')),
            replyTo: [MailReplyTo::booking($booking)],
        );
    }

    public function headers(): Headers
    {
        $reference = BookingMailThread::reference($this->booking());

        return new Headers(
            references: [$reference],
            text: [
                'In-Reply-To' => '<'.$reference.'>',
            ],
        );
    }

    public function content(): Content
    {
        $booking = $this->booking();

        return new Content(
            markdown: 'mail.booking.comment',
            with: [
                'comment' => $this->comment,
                'booking' => $booking,
                'author' => $this->comment->author,
                'recipient' => $this->recipient,
                'url' => $this->comment->url(),
                ...MailPreference::footerViewData($this->recipient, MailGroup::Bookings, $booking),
            ],
        );
    }

    private function booking(): Booking
    {
        $commentable = $this->comment->commentable;

        assert($commentable instanceof Booking);

        return $commentable;
    }
}
