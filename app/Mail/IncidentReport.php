<?php

namespace App\Mail;

use App\Models\User;
use App\Notifications\IncidentReportFailureNotification;
use App\Support\Incident\IncidentReportData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class IncidentReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 10;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600, 10800, 21600, 43200, 86400];

    public function __construct(public IncidentReportData $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Incident Report'),
            replyTo: [$this->report->reporterMailAddress()],
            tags: ['incident'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.incident.report',
            with: ['report' => $this->report],
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Incident report delivery failed after all retry attempts.', [
            'team_name' => $this->report->teamName,
            'reporter_email' => $this->report->reporterEmail,
            'exception' => $exception,
        ]);

        $admins = User::query()
            ->where('is_system_admin', true)
            ->where('suspended', false)
            ->whereNotNull('password')
            ->whereNotNull('email_verified_at')
            ->whereNotNull('email')
            ->get();

        if ($admins->isEmpty()) {
            Log::critical('Incident report delivery failed without an eligible system administrator to alert.', [
                'team_name' => $this->report->teamName,
                'reporter_email' => $this->report->reporterEmail,
            ]);

            return;
        }

        Notification::send(
            $admins,
            new IncidentReportFailureNotification($this->report, $exception->getMessage()),
        );
    }
}
