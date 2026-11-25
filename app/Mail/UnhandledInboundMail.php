<?php

namespace App\Mail;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use App\Models\InboundMail;
use App\Support\InboundMailResourceLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnhandledInboundMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public InboundMail $inboundMail)
    {
        $this->afterCommit();
        $this->inboundMail->loadMissing('resource');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Could not process an inbound email'),
        );
    }

    public function content(): Content
    {
        $resource = $this->inboundMail->resource;

        return new Content(
            markdown: 'mail.inbound.unhandled',
            with: [
                'inboundMail' => $this->inboundMail,
                'resourceLabel' => InboundMailResourceLink::label($resource),
                'resourceUrl' => InboundMailResourceLink::url($resource),
                'reviewUrl' => InboundMailResource::getUrl('view', ['record' => $this->inboundMail], panel: 'admin'),
            ],
        );
    }
}
