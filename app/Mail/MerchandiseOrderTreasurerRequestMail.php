<?php

namespace App\Mail;

use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use App\Models\User;
use App\Support\TeamMailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Number;

class MerchandiseOrderTreasurerRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    /**
     * @var list<array{description: string, total: string}>
     */
    public array $billableLines = [];

    public string $itemsSubtotalLabel;

    public string $orderDiscountLabel;

    public string $amountDueLabel;

    public bool $hasOrderDiscount;

    public function __construct(
        public MerchandiseOrder $order,
        public User $actor,
    ) {
        $this->afterCommit();
        $this->order->loadMissing(['team', 'user', 'lines']);

        $this->billableLines = $this->order->lines
            ->map(function (MerchandiseOrderLine $line): array {
                $description = $line->quantity.'× '.$line->item_title;

                if (filled($line->variant_label)) {
                    $description .= ' ('.$line->variant_label.')';
                }

                return [
                    'description' => $description,
                    'total' => Number::currency($line->lineTotal(), 'GBP', 'en_GB'),
                ];
            })
            ->values()
            ->all();

        $this->itemsSubtotalLabel = Number::currency($this->order->itemsSubtotal(), 'GBP', 'en_GB');
        $this->orderDiscountLabel = Number::currency((float) $this->order->order_discount, 'GBP', 'en_GB');
        $this->amountDueLabel = Number::currency((float) $this->order->amount_due, 'GBP', 'en_GB');
        $this->hasOrderDiscount = (float) $this->order->order_discount > 0;
    }

    public function envelope(): Envelope
    {
        $memberName = $this->order->user?->name ?? __('A member');
        $team = $this->order->team;

        $envelope = new Envelope(
            subject: __('Please invoice :name', [
                'name' => $memberName,
            ]),
            replyTo: filled($this->actor->email)
                ? [new Address($this->actor->email, $this->actor->name)]
                : [],
        );

        if ($team === null) {
            return $envelope;
        }

        return TeamMailIdentity::applyToEnvelope(
            $envelope,
            $team,
            filled($this->actor->email)
                ? new Address($this->actor->email, $this->actor->name)
                : null,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.merchandise.treasurer-request',
            with: [
                'order' => $this->order,
                'actor' => $this->actor,
                'member' => $this->order->user,
                'billableLines' => $this->billableLines,
                'itemsSubtotalLabel' => $this->itemsSubtotalLabel,
                'orderDiscountLabel' => $this->orderDiscountLabel,
                'amountDueLabel' => $this->amountDueLabel,
                'hasOrderDiscount' => $this->hasOrderDiscount,
            ],
        );
    }
}
