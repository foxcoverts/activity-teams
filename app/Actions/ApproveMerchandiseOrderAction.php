<?php

namespace App\Actions;

use App\Enums\MerchandiseOrderStatus;
use App\Models\MerchandiseOrder;
use App\Models\User;
use App\Notifications\MerchandiseOrderApprovedNotification;
use App\Support\ResolveMerchandiseOrderSubmittedInbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApproveMerchandiseOrderAction
{
    public function __invoke(
        MerchandiseOrder $order,
        User $user,
        ?string $approvalNote = null,
        ?string $treasurerEmail = null,
    ): MerchandiseOrder {
        $order->loadMissing(['team', 'user', 'lines']);

        if (! $user->can('approve', $order)) {
            throw ValidationException::withMessages([
                'order' => __('You cannot approve this order.'),
            ]);
        }

        if (! $order->canBeReviewed()) {
            throw ValidationException::withMessages([
                'order' => __('This order can no longer be approved.'),
            ]);
        }

        $subtotal = $order->itemsSubtotal();

        if ((float) $order->order_discount > $subtotal) {
            throw ValidationException::withMessages([
                'order_discount' => __('The order discount cannot be more than the items subtotal.'),
            ]);
        }

        $amountDue = $order->total();

        if ($amountDue > 0) {
            Validator::make(
                ['treasurer_email' => $treasurerEmail],
                ['treasurer_email' => ['required', 'email', 'max:255']],
            )->validate();
        }

        $order = DB::transaction(function () use ($order, $approvalNote, $amountDue): MerchandiseOrder {
            foreach ($order->lines as $line) {
                if ($line->approved_unit_price === null) {
                    $line->update([
                        'approved_unit_price' => $line->unit_price,
                    ]);
                }
            }

            $order->update([
                'status' => MerchandiseOrderStatus::Approved,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'approval_note' => filled($approvalNote) ? trim($approvalNote) : null,
                'amount_due' => number_format($amountDue, 2, '.', ''),
                'paid_at' => $amountDue > 0 ? null : now(),
            ]);

            return $order->refresh()->load(['lines', 'user', 'team']);
        });

        app(ResolveMerchandiseOrderSubmittedInbox::class)($order);

        if ($amountDue > 0) {
            app(RequestMerchandiseOrderTreasurerInvoiceAction::class)(
                $order,
                $user,
                (string) $treasurerEmail,
            );
        } else {
            $member = $order->user;

            if ($member instanceof User && ! $member->is($user)) {
                $member->notify(new MerchandiseOrderApprovedNotification($order));
            }
        }

        return $order;
    }
}
