<?php

namespace App\Notifications;

use App\Enums\MailChannel;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Notifications\Concerns\RecordsInbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskHeldNotification extends Notification
{
    use Queueable;
    use RecordsInbox;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Task $task, public string $body)
    {
        $this->afterCommit();
    }

    public function inboxBody(object $notifiable): string
    {
        return $this->body;
    }

    public function inboxUrl(object $notifiable): ?string
    {
        return TaskResource::getUrl('view', ['record' => $this->task], panel: 'teams', tenant: $this->task->team);
    }

    public function inboxChannel(): ?MailChannel
    {
        return MailChannel::TasksMail;
    }

    public function inboxInAppOnly(): bool
    {
        return true;
    }

    public function inboxSubject(): ?Task
    {
        return $this->task;
    }

    /**
     * @return array<string, mixed>
     */
    public function inboxRebuild(): array
    {
        return [
            'task_id' => $this->task->getKey(),
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
            Task::query()->findOrFail($rebuild['task_id']),
            (string) $rebuild['body'],
        );
    }
}
