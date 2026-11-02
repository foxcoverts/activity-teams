<?php

namespace App\Mail;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Keys\KeyResource;
use App\Models\Booking;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Team;
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
        return new Content(
            markdown: 'mail.inbound.unhandled',
            with: [
                'inboundMail' => $this->inboundMail,
                'resourceLabel' => $this->resourceLabel(),
                'resourceUrl' => $this->resourceUrl(),
                'reviewUrl' => InboundMailResource::getUrl('view', ['record' => $this->inboundMail], panel: 'admin'),
            ],
        );
    }

    protected function resourceLabel(): ?string
    {
        $resource = $this->inboundMail->resource;

        return match (true) {
            $resource instanceof Booking => collect([
                $resource->activityTitle(),
                $resource->audience?->name,
                filled($resource->group_name) ? $resource->group_name : null,
            ])->filter()->implode(' · '),
            $resource instanceof Key => $resource->name,
            $resource instanceof Team => $resource->name,
            default => null,
        };
    }

    protected function resourceUrl(): ?string
    {
        $resource = $this->inboundMail->resource;

        return match (true) {
            $resource instanceof Booking => BookingResource::getUrl('view', ['record' => $resource], panel: 'manage'),
            $resource instanceof Key => KeyResource::getUrl('view', ['record' => $resource], panel: 'manage'),
            $resource instanceof Team => TeamResource::getUrl('view', ['record' => $resource], panel: 'admin'),
            default => null,
        };
    }
}
