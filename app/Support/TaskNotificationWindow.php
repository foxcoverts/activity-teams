<?php

namespace App\Support;

use App\Jobs\SendTaskNotification;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TaskNotificationWindow
{
    public const UPDATED = 'updated';

    public const CANCELLED = 'cancelled';

    public const INVITE = 'invite';

    /**
     * @param  list<string>  $changes
     */
    public static function push(Task $task, string $kind, ?string $assigneeUserId = null, array $changes = []): void
    {
        $token = Cache::lock(self::lockKey($task), 10)->block(5, function () use ($task, $kind, $assigneeUserId, $changes): string {
            $pending = Cache::get(self::cacheKey($task), [
                'token' => null,
                'kinds' => [],
                'assignee_user_id' => null,
                'changes' => [],
            ]);

            $pending['token'] = (string) Str::uuid();
            $pending['kinds'][] = $kind;
            $pending['changes'] = array_values(array_unique(array_merge(
                $pending['changes'] ?? [],
                array_values(array_diff($changes, ['updated_at', 'sequence', 'started_at', 'completed_at'])),
            )));

            if ($assigneeUserId !== null) {
                $pending['assignee_user_id'] = $assigneeUserId;
            }

            Cache::put(self::cacheKey($task), $pending, now()->addMinutes(10));

            return $pending['token'];
        });

        SendTaskNotification::dispatch($task, $token)
            ->delay(now()->addMinute());
    }

    /**
     * @return array{token: string, kinds: array<int, string>, assignee_user_id: ?string, changes: array<int, string>}|null
     */
    public static function claim(Task $task, string $token): ?array
    {
        return Cache::lock(self::lockKey($task), 10)->block(5, function () use ($task, $token): ?array {
            $pending = Cache::get(self::cacheKey($task));

            if (! is_array($pending) || ($pending['token'] ?? null) !== $token) {
                return null;
            }

            Cache::forget(self::cacheKey($task));

            return $pending;
        });
    }

    public static function cacheKey(Task $task): string
    {
        return 'task-notification:'.$task->getKey();
    }

    public static function lockKey(Task $task): string
    {
        return 'task-notification-lock:'.$task->getKey();
    }
}
