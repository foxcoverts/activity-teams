<?php

namespace App\Livewire;

use App\Actions\PostCommentAction;
use App\Enums\MembershipRole;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BookingComments extends Component
{
    public ?Booking $record = null;

    #[Locked]
    public ?string $highlightId = null;

    public string $body = '';

    public bool $showOlder = false;

    public ?string $editingId = null;

    public string $editBody = '';

    public function mount(?Booking $record = null, ?string $highlightId = null): void
    {
        abort_unless($record instanceof Booking, 404);

        $this->record = $this->booking($record->getKey());
        $this->highlightId = filled($highlightId) ? $highlightId : null;

        abort_unless($this->viewer()?->can('view', $this->record) ?? false, 403);
    }

    public function post(): void
    {
        $booking = $this->currentBooking();
        $viewer = $this->viewer();
        abort_unless($viewer instanceof User && $viewer->can('create', [Comment::class, $booking]), 403);

        $this->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        (new PostCommentAction)($booking, $viewer, $this->body);

        $this->body = '';
        $this->record = $this->booking($booking->getKey());

        Notification::make()->success()->title(__('Comment posted.'))->send();
    }

    public function startEdit(string $commentId): void
    {
        $comment = $this->comment($commentId);
        abort_unless($this->viewer()?->can('update', $comment) ?? false, 403);

        $this->editingId = $comment->getKey();
        $this->editBody = $comment->body;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editBody = '';
    }

    public function saveEdit(): void
    {
        abort_unless(filled($this->editingId), 404);

        $comment = $this->comment($this->editingId);
        abort_unless($this->viewer()?->can('update', $comment) ?? false, 403);

        $this->validate([
            'editBody' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update(['body' => trim($this->editBody)]);
        $this->cancelEdit();

        Notification::make()->success()->title(__('Comment updated.'))->send();
    }

    public function remove(string $commentId): void
    {
        $comment = $this->comment($commentId);
        $viewer = $this->viewer();

        if ($comment->trashed()) {
            abort_unless($viewer?->can('forceDelete', $comment) ?? false, 403);
            $comment->forceDelete();
        } else {
            abort_unless($viewer?->can('delete', $comment) ?? false, 403);
            $comment->delete();
        }

        if ($this->editingId === $commentId) {
            $this->cancelEdit();
        }

        Notification::make()->success()->title(__('Removed'))->send();
    }

    public function restore(string $commentId): void
    {
        $comment = $this->comment($commentId);
        abort_unless($this->viewer()?->can('restore', $comment) ?? false, 403);

        $comment->restore();

        Notification::make()->success()->title(__('Restored'))->send();
    }

    public function showOlderComments(): void
    {
        $this->showOlder = true;
    }

    public function render(): View
    {
        $booking = $this->currentBooking();
        $viewer = $this->viewer();
        abort_unless($viewer?->can('view', $booking) ?? false, 403);

        $comments = $this->visibleComments($booking, $viewer);
        $target = $this->target($comments);
        $older = collect();
        $visible = $comments;

        if ($target instanceof Comment) {
            $older = $comments
                ->filter(fn (Comment $comment): bool => $this->isOlderThan($comment, $target))
                ->values();
            $rest = $comments
                ->reject(fn (Comment $comment): bool => $this->isOlderThan($comment, $target))
                ->values();
            $visible = $this->showOlder ? $comments : $rest;
        } else {
            $this->highlightId = null;
        }

        $canPost = $viewer->can('create', [Comment::class, $booking]);
        $hasOlder = $older->isNotEmpty();

        return view('filament.bookings.comments', [
            'rows' => $visible->map(fn (Comment $comment): array => $this->row($comment, $booking, $viewer, $target))->values(),
            'canPost' => $canPost,
            'hasOlder' => $hasOlder,
            'showOlder' => $this->showOlder,
            'highlightId' => $target?->getKey(),
            'showPanel' => $canPost || $visible->isNotEmpty() || ($hasOlder && ! $this->showOlder),
            'viewerAvatar' => $viewer->gravatarUrl(),
            'viewerName' => $viewer->name,
        ]);
    }

    /**
     * @return Collection<int, Comment>
     */
    private function visibleComments(Booking $booking, User $viewer): Collection
    {
        return $booking->comments()
            ->withTrashed()
            ->with(['author.teams'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(fn (Comment $comment) => $comment->setRelation('commentable', $booking))
            ->filter(fn (Comment $comment): bool => $viewer->can('view', $comment))
            ->values();
    }

    /**
     * @param  Collection<int, Comment>  $comments
     */
    private function target(Collection $comments): ?Comment
    {
        if ($this->highlightId === null) {
            return null;
        }

        $match = $comments->first(
            fn (Comment $comment): bool => $comment->getKey() === $this->highlightId,
        );

        return $match instanceof Comment ? $match : null;
    }

    private function isOlderThan(Comment $comment, Comment $target): bool
    {
        if ($comment->created_at?->lt($target->created_at)) {
            return true;
        }

        return $comment->created_at?->equalTo($target->created_at)
            && strcmp((string) $comment->getKey(), (string) $target->getKey()) < 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Comment $comment, Booking $booking, User $viewer, ?Comment $target): array
    {
        $author = $comment->author;
        $team = $booking->commentTeam();

        return [
            'id' => $comment->getKey(),
            'body' => $comment->body,
            'author' => $author?->name ?? __('someone'),
            'avatar' => $author?->gravatarUrl() ?? (new User)->gravatarUrl(),
            'when' => $this->when($comment, $viewer),
            'when_full' => $this->whenFull($comment, $viewer),
            'when_iso' => $this->whenIso($comment, $viewer),
            'state' => $this->state($comment),
            'trashed' => $comment->trashed(),
            'highlight' => $target instanceof Comment && $comment->is($target),
            'is_manager' => $author instanceof User && $team !== null && $author->roleOn($team) === MembershipRole::Manager,
            'is_lead' => $author instanceof User && $booking->isLead($author),
            'can_edit' => $viewer->can('update', $comment),
            'can_remove' => $comment->trashed()
                ? $viewer->can('forceDelete', $comment)
                : $viewer->can('delete', $comment),
            'can_restore' => $viewer->can('restore', $comment),
            'editing' => $this->editingId === $comment->getKey(),
        ];
    }

    private function when(Comment $comment, User $viewer): string
    {
        $at = $this->postedAt($comment, $viewer);

        if ($at === null) {
            return '';
        }

        if ($at->greaterThan(now()->subMinute())) {
            return __('just now');
        }

        return $at->diffForHumans();
    }

    private function whenFull(Comment $comment, User $viewer): string
    {
        return $this->postedAt($comment, $viewer)?->format('j M Y, H:i') ?? '';
    }

    private function whenIso(Comment $comment, User $viewer): string
    {
        return $this->postedAt($comment, $viewer)?->toIso8601String() ?? '';
    }

    private function postedAt(Comment $comment, User $viewer): ?Carbon
    {
        return $comment->created_at?->timezone($viewer->timezoneName());
    }

    private function state(Comment $comment): string
    {
        if ($comment->trashed()) {
            return __('Deleted');
        }

        $created = $comment->created_at;
        $updated = $comment->updated_at;

        if ($created !== null && $updated !== null && $updated->gt($created)) {
            return __('Edited');
        }

        return '';
    }

    private function currentBooking(): Booking
    {
        abort_unless($this->record instanceof Booking, 404);

        return $this->booking($this->record->getKey());
    }

    private function comment(string $id): Comment
    {
        $booking = $this->currentBooking();

        $comment = $booking->comments()->withTrashed()->whereKey($id)->first();
        abort_unless($comment instanceof Comment, 404);

        return $comment->setRelation('commentable', $booking);
    }

    private function viewer(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function booking(string $id): Booking
    {
        return Booking::query()->findOrFail($id);
    }
}
