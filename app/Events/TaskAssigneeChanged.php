<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigneeChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public Task $task,
        public User $assignee,
        public bool $cancelled = false,
        public bool $updateOnly = false,
        public array $changes = [],
    ) {}
}
