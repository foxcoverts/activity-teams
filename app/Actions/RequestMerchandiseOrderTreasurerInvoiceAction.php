<?php

namespace App\Actions;

use App\Mail\MerchandiseOrderTreasurerRequestMail;
use App\Models\MerchandiseOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestMerchandiseOrderTreasurerInvoiceAction
{
    public function __invoke(MerchandiseOrder $order, User $actor, string $treasurerEmail): void
    {
        $order->loadMissing(['team', 'user', 'lines']);

        if (! $actor->can('requestTreasurerInvoice', $order)) {
            throw ValidationException::withMessages([
                'order' => __('You cannot request a treasurer invoice for this order.'),
            ]);
        }

        if (! $order->isUnpaid()) {
            throw ValidationException::withMessages([
                'order' => __('This order does not need a treasurer invoice.'),
            ]);
        }

        $validated = Validator::make(
            ['treasurer_email' => $treasurerEmail],
            ['treasurer_email' => ['required', 'email', 'max:255']],
        )->validate();

        $email = strtolower(trim($validated['treasurer_email']));
        $member = $order->user;

        $cc = [];

        if ($member instanceof User && filled($member->email)) {
            $cc[] = $member->email;
        }

        if (filled($actor->email)) {
            $cc[] = $actor->email;
        }

        $cc = collect($cc)
            ->map(fn (string $address): string => strtolower(trim($address)))
            ->filter()
            ->unique()
            ->reject(fn (string $address): bool => $address === $email)
            ->values()
            ->all();

        $mailable = new MerchandiseOrderTreasurerRequestMail($order, $actor);

        $pending = Mail::to($email);

        if ($cc !== []) {
            $pending->cc($cc);
        }

        $pending->queue($mailable);
    }
}
