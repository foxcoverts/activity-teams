<?php

namespace App\Mail;

use App\Enums\CalendarMethod;
use App\Enums\MailGroup;
use App\Enums\TaskAttendeeStatus;
use App\Mail\Concerns\AttachesTaskCalendar;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\User;
use App\Support\MailPreference;
use App\Support\MailReplyTo;
use App\Support\TaskAssigneeResponseLinks;
use App\Support\TaskMailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class TaskInviteMail extends Mailable
{
    use AttachesTaskCalendar;
    use Queueable, SerializesModels;

    public ?TaskAttendance $attendance = null;

    public function __construct(
        public Task $task,
        public User $assignee,
    ) {
        $this->task->loadMissing(['venue', 'team', 'attendance']);
        $this->attendance = $this->task->attendance;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: TaskMailThread::subject($this->task, __('Assignment')),
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

    public function content(): Content
    {
        $token = $this->attendance?->token;
        $choiceUrls = TaskAssigneeResponseLinks::choices($this->task, $this->assignee, $this->attendance);

        return new Content(
            markdown: 'mail.task.invite',
            with: [
                'task' => $this->task,
                'assignee' => $this->assignee,
                'attendance' => $this->attendance,
                'title' => $this->heading(),
                'intro' => $this->intro(),
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

    private function heading(): string
    {
        return $this->attendance?->status === TaskAttendeeStatus::NeedsAction
            ? __('You have been invited')
            : __('You have been assigned a task');
    }

    private function intro(): string
    {
        return $this->attendance?->status === TaskAttendeeStatus::NeedsAction
            ? __('You\'ve been invited to complete a team task.')
            : __('You have been assigned a team task.');
    }
}
