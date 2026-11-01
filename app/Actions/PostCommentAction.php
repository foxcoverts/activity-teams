<?php

namespace App\Actions;

use App\Contracts\Commentable;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Mail\BookingCommentMail;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\InboxNotification;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BookingCommentNotification;
use App\Support\MailPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PostCommentAction
{
    public function __invoke(Commentable $commentable, User $author, string $body): Comment
    {
        abort_unless($author->can('create', [Comment::class, $commentable]), 403);

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => __('Enter a comment.'),
            ]);
        }

        $comment = $commentable->comments()->create([
            'author_id' => $author->getKey(),
            'body' => $body,
        ]);

        $comment->setRelation('commentable', $commentable instanceof Booking ? $commentable : $comment->commentable);
        $comment->setRelation('author', $author);

        $this->notify($comment);

        return $comment;
    }

    private function notify(Comment $comment): void
    {
        $commentable = $comment->commentable;

        if (! $commentable instanceof Booking) {
            return;
        }

        $commentable->loadMissing(['activity', 'attendees', 'team']);

        foreach ($this->recipients($commentable, $comment->author) as $recipient) {
            $recipient = $recipient->fresh() ?? $recipient;
            $recipient->notify(new BookingCommentNotification($comment));

            if (! $recipient->hasVerifiedEmail() || ! MailPreference::allows($recipient, MailChannel::BookingsComments, $commentable)) {
                continue;
            }

            Mail::to($recipient)->send(new BookingCommentMail($comment, $recipient));
            $this->markInboxRead($recipient, $comment);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Booking $booking, ?User $author): Collection
    {
        $team = $booking->team;
        $recipients = collect();

        foreach ($booking->attendees as $attendee) {
            if ($this->skipRecipient($attendee, $author)) {
                continue;
            }

            $declined = $attendee->attendance?->status === BookingAttendeeStatus::Declined;
            $manages = $team instanceof Team && $attendee->manages($team);

            if ($declined && ! $manages) {
                continue;
            }

            $recipients->put($attendee->getKey(), $attendee);
        }

        if ($team instanceof Team) {
            foreach ($team->activeManagers()->get() as $manager) {
                if ($this->skipRecipient($manager, $author)) {
                    continue;
                }

                $recipients->put($manager->getKey(), $manager);
            }
        }

        return $recipients->values();
    }

    private function skipRecipient(User $recipient, ?User $author): bool
    {
        return $recipient->isSuspended()
            || ($author instanceof User && $recipient->is($author));
    }

    private function markInboxRead(User $recipient, Comment $comment): void
    {
        $row = $recipient->notifications()
            ->where('type', BookingCommentNotification::class)
            ->latest()
            ->get()
            ->first(
                fn (InboxNotification $notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $comment->getKey(),
            );

        $row?->markReadForDelivery();
    }
}
