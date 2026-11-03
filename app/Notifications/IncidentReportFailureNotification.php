<?php

namespace App\Notifications;

use App\Support\Incident\IncidentReportData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncidentReportFailureNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public IncidentReportData $report,
        public string $exceptionMessage,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Incident report delivery failed'))
            ->markdown('mail.incident.report-failure', [
                'teamName' => $this->report->teamName,
                'reporterName' => $this->report->reporterName,
                'reporterEmail' => $this->report->reporterEmail,
                'exceptionMessage' => $this->exceptionMessage,
            ]);
    }
}
