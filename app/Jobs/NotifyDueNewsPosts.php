<?php

namespace App\Jobs;

use App\Models\NewsPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyDueNewsPosts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        NewsPost::query()
            ->with('team')
            ->dueForNotification()
            ->each(function (NewsPost $post): void {
                $post->notifyMembers();
            });
    }
}
