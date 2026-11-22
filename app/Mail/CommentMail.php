<?php

namespace App\Mail;

use App\Enums\MailGroup;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Support\BookingMailThread;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use App\Support\TaskMailThread;
use App\Support\TeamMailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class CommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Comment $comment,
        public User $recipient,
    ) {
        $this->afterCommit();
        $this->comment->loadMissing(['author', 'commentable']);
    }

    public function envelope(): Envelope
    {
        $commentable = $this->commentable();

        if ($commentable instanceof Booking) {
            $commentable->loadMissing(['activity', 'venue', 'team']);
            $operational = MailReplyTo::booking($commentable);
            $envelope = new Envelope(
                subject: BookingMailThread::subject($commentable, __('Re')),
                replyTo: [$operational],
            );

            if ($commentable->team === null) {
                return $envelope;
            }

            return TeamMailIdentity::applyToEnvelope($envelope, $commentable->team, $operational);
        }

        $commentable->loadMissing(['venue', 'team']);
        $operational = MailReplyTo::task($commentable);
        $envelope = new Envelope(
            subject: TaskMailThread::subject($commentable, __('Re')),
            replyTo: [$operational],
        );

        if ($commentable->team === null) {
            return $envelope;
        }

        return TeamMailIdentity::applyToEnvelope($envelope, $commentable->team, $operational);
    }

    public function headers(): Headers
    {
        $commentable = $this->commentable();
        $reference = $commentable instanceof Booking
            ? BookingMailThread::reference($commentable)
            : TaskMailThread::reference($commentable);

        return new Headers(
            references: [$reference],
            text: [
                'In-Reply-To' => '<'.$reference.'>',
            ],
        );
    }

    public function content(): Content
    {
        $commentable = $this->commentable();
        $group = $commentable instanceof Booking ? MailGroup::Bookings : MailGroup::Tasks;
        $parentLabel = $commentable instanceof Booking
            ? ($commentable->activityTitle() ?? __('a booking'))
            : $commentable->summary;

        return new Content(
            markdown: 'mail.comment',
            with: [
                'comment' => $this->comment,
                'author' => $this->comment->author,
                'recipient' => $this->recipient,
                'parentLabel' => $parentLabel,
                'url' => $this->comment->url(),
                ...MailPreference::footerViewData($this->recipient, $group, $commentable),
            ],
        );
    }

    private function commentable(): Booking|Task
    {
        $commentable = $this->comment->commentable;

        assert($commentable instanceof Booking || $commentable instanceof Task);

        return $commentable;
    }
}
