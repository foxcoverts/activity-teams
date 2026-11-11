<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Enums\MailGroup;
use App\Mail\Concerns\AttachesTaskCalendar;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use App\Support\TaskAssigneeResponseLinks;
use App\Support\TaskChangeSummary;
use App\Support\TaskMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class TaskStatusMail extends Mailable
{
    use AttachesTaskCalendar;
    use Queueable, SerializesModels;

    public ?TaskAttendance $attendance = null;

    /**
     * @param  list<string>  $changes
     */
    public function __construct(
        public Task $task,
        public User $assignee,
        public array $changes = [],
    ) {
        $this->task->loadMissing(['venue', 'team', 'attendance']);
        $this->attendance = $task->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: TaskMailThread::subject($this->task, $this->subjectKind()),
            replyTo: [MailReplyTo::task($this->task)],
            using: [
                fn (Email $message) => $this->attachCalendarData($message),
            ],
        );
    }

    public function headers(): Headers
    {
        return TaskMailThread::headers($this->task);
    }

    protected function subjectKind(): string
    {
        return __('Updated');
    }

    public function content(): Content
    {
        $token = $this->attendance?->token;
        $changeList = new TaskChangeSummary($this->task, $this->changes);
        $choiceUrls = TaskAssigneeResponseLinks::choices($this->task, $this->assignee, $this->attendance);

        return new Content(
            markdown: 'mail.task.status',
            with: [
                'task' => $this->task,
                'assignee' => $this->assignee,
                'attendance' => $this->attendance,
                'title' => $this->heading(),
                'intro' => $this->intro(),
                'changeList' => $changeList,
                'choiceUrls' => $choiceUrls,
                'respondUrl' => $choiceUrls === [] && filled($token)
                    ? TaskAssigneeResponseLinks::url($this->task, $this->assignee, $token)
                    : null,
                ...MailPreference::footerViewData($this->assignee, MailGroup::Tasks, $this->task),
            ],
        );
    }

    protected function task(): Task
    {
        return $this->task;
    }

    protected function assignee(): User
    {
        return $this->assignee;
    }

    protected function calendarMethod(): CalendarMethod
    {
        return CalendarMethod::Request;
    }

    protected function heading(): string
    {
        return $this->statusHeading();
    }

    protected function intro(): string
    {
        return $this->statusIntro();
    }

    protected function statusHeading(): string
    {
        return __('Task updated');
    }

    protected function statusIntro(): string
    {
        return __('A task assigned to you has changed.');
    }
}
