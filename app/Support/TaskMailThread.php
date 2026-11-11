<?php

namespace App\Support;

use App\Models\Task;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

class TaskMailThread
{
    public static function headers(Task $task): Headers
    {
        return new Headers(
            references: [self::reference($task)],
        );
    }

    public static function subject(Task $task, string $kind): string
    {
        $task->loadMissing(['venue']);

        $venue = filled($task->location?->name ?? $task->venue?->name)
            ? ($task->location?->name ?? $task->venue?->name)
            : __('No venue');

        $due = $task->due_at !== null
            ? localDate($task->due_at, config('app.timezone'))->isoFormat('ddd D MMM YYYY HH:mm')
            : __('No due date');

        return __(':kind: :summary at :venue, due :due', [
            'kind' => $kind,
            'summary' => $task->summary,
            'venue' => $venue,
            'due' => $due,
        ]);
    }

    public static function reference(Task $task): string
    {
        return sprintf(
            'task-%s-sequence-%s@%s',
            $task->getKey(),
            $task->sequence,
            Str::afterLast($task->uid, '@'),
        );
    }
}
