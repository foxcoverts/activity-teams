<?php

namespace App\Policies;

use App\Contracts\Commentable;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Team;
use App\Models\User;

class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        $commentable = $comment->commentable;

        if (! $commentable instanceof Commentable || ! $user->can('view', $commentable)) {
            return false;
        }

        if (! $comment->trashed()) {
            return true;
        }

        return $this->isAuthor($user, $comment) || $this->managesTeam($user, $commentable);
    }

    public function create(User $user, Commentable $commentable): bool
    {
        if (! $commentable instanceof Booking) {
            return false;
        }

        return $user->can('respond', [$commentable, $user]);
    }

    public function update(User $user, Comment $comment): bool
    {
        return ! $comment->trashed() && $this->isAuthor($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        $commentable = $comment->commentable;

        return $this->isAuthor($user, $comment)
            || ($commentable instanceof Commentable && $this->managesTeam($user, $commentable));
    }

    public function restore(User $user, Comment $comment): bool
    {
        if (! $comment->trashed()) {
            return false;
        }

        $commentable = $comment->commentable;

        return $commentable instanceof Commentable && $this->managesTeam($user, $commentable);
    }

    public function forceDelete(User $user, Comment $comment): bool
    {
        if (! $comment->trashed()) {
            return false;
        }

        $commentable = $comment->commentable;

        return $this->isAuthor($user, $comment)
            || ($commentable instanceof Commentable && $this->managesTeam($user, $commentable));
    }

    private function isAuthor(User $user, Comment $comment): bool
    {
        return filled($comment->author_id) && (string) $comment->author_id === (string) $user->getKey();
    }

    private function managesTeam(User $user, Commentable $commentable): bool
    {
        $team = $commentable->commentTeam();

        return $team instanceof Team && $user->manages($team);
    }
}
