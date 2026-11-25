<?php

namespace App\Actions;

use App\Enums\MerchandiseAvailability;
use App\Enums\MerchandiseOrderStatus;
use App\Enums\TeamFeature;
use App\Models\MerchandiseBasketLine;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use App\Models\Team;
use App\Models\User;
use App\Notifications\MerchandiseOrderSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitMerchandiseOrderAction
{
    public function __invoke(Team $team, User $user): MerchandiseOrder
    {
        if (! $team->featureEnabled(TeamFeature::Shop) || ! $user->isCurrentMemberOf($team)) {
            throw ValidationException::withMessages([
                'basket' => __('You cannot submit an order for this team.'),
            ]);
        }

        $lines = MerchandiseBasketLine::query()
            ->forTeamUser($team, $user)
            ->with(['item', 'variant'])
            ->orderBy('created_at')
            ->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'basket' => __('Your basket is empty.'),
            ]);
        }

        foreach ($lines as $line) {
            $item = $line->item;
            $variant = $line->variant;

            if ($item === null
                || $variant === null
                || $item->availability !== MerchandiseAvailability::Available
                || $variant->availability !== MerchandiseAvailability::Available
                || $item->team_id !== $team->getKey()
            ) {
                throw ValidationException::withMessages([
                    'basket' => __('One or more basket items are no longer available. Remove them and try again.'),
                ]);
            }
        }

        $order = DB::transaction(function () use ($team, $user, $lines): MerchandiseOrder {
            $order = MerchandiseOrder::query()->create([
                'team_id' => $team->getKey(),
                'user_id' => $user->getKey(),
                'status' => MerchandiseOrderStatus::Submitted,
                'submitted_at' => now(),
            ]);

            foreach ($lines->values() as $index => $line) {
                MerchandiseOrderLine::query()->create([
                    'merchandise_order_id' => $order->getKey(),
                    'merchandise_item_id' => $line->merchandise_item_id,
                    'merchandise_item_variant_id' => $line->merchandise_item_variant_id,
                    'item_title' => $line->item->title,
                    'variant_label' => $line->variant->label,
                    'unit_price' => $line->variant->price,
                    'quantity' => $line->quantity,
                    'personalisation' => $line->personalisation,
                    'sort' => $index,
                ]);
            }

            MerchandiseBasketLine::query()
                ->forTeamUser($team, $user)
                ->delete();

            return $order->load(['lines', 'user', 'team']);
        });

        $this->notifyManagers($order);

        return $order;
    }

    private function notifyManagers(MerchandiseOrder $order): void
    {
        $team = $order->team;

        if (! $team instanceof Team) {
            return;
        }

        $managers = $team->managers()
            ->where('users.suspended', false)
            ->whereNotNull('users.password')
            ->whereNotNull('users.email_verified_at')
            ->get();

        foreach ($managers as $manager) {
            if ((string) $manager->getKey() === (string) $order->user_id) {
                continue;
            }

            $manager->notify(new MerchandiseOrderSubmittedNotification($order));
        }
    }
}
