<?php

namespace App\Actions;

use App\Enums\MerchandiseOrderStatus;
use App\Models\MerchandiseOrder;
use App\Models\User;
use App\Notifications\MerchandiseOrderRejectedNotification;
use App\Support\ResolveMerchandiseOrderSubmittedInbox;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RejectMerchandiseOrderAction
{
    public function __invoke(MerchandiseOrder $order, User $user, string $reason): MerchandiseOrder
    {
        $order->loadMissing(['team', 'user']);

        if (! $user->can('reject', $order)) {
            throw ValidationException::withMessages([
                'order' => __('You cannot reject this order.'),
            ]);
        }

        if (! $order->canBeReviewed()) {
            throw ValidationException::withMessages([
                'order' => __('This order can no longer be rejected.'),
            ]);
        }

        $validated = Validator::make(
            ['rejection_reason' => $reason],
            [
                'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
            ],
        )->validate();

        $order->update([
            'status' => MerchandiseOrderStatus::Rejected,
            'rejected_at' => now(),
            'approved_at' => null,
            'approval_note' => null,
            'rejection_reason' => trim($validated['rejection_reason']),
        ]);

        $order = $order->refresh()->load(['user', 'team', 'lines']);

        app(ResolveMerchandiseOrderSubmittedInbox::class)($order);

        $member = $order->user;

        if ($member instanceof User && ! $member->is($user)) {
            $member->notify(new MerchandiseOrderRejectedNotification($order));
        }

        return $order;
    }
}
