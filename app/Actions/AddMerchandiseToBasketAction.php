<?php

namespace App\Actions;

use App\Enums\MerchandiseAvailability;
use App\Enums\TeamFeature;
use App\Models\MerchandiseBasketLine;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use App\Models\User;
use App\Support\MerchandisePersonalisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddMerchandiseToBasketAction
{
    /**
     * @param  array<string, mixed>  $personalisationAnswers
     */
    public function __invoke(
        Team $team,
        User $user,
        MerchandiseItem $item,
        MerchandiseItemVariant $variant,
        int $quantity,
        array $personalisationAnswers = [],
    ): MerchandiseBasketLine {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('Quantity must be at least 1.'),
            ]);
        }

        if (! $team->featureEnabled(TeamFeature::Shop) || ! $user->isCurrentMemberOf($team)) {
            throw ValidationException::withMessages([
                'item' => __('You cannot add this item to the basket.'),
            ]);
        }

        $item->loadMissing(['team', 'variants']);

        if ($item->team_id !== $team->getKey()
            || $item->availability !== MerchandiseAvailability::Available
            || $variant->merchandise_item_id !== $item->getKey()
            || $variant->availability !== MerchandiseAvailability::Available
        ) {
            throw ValidationException::withMessages([
                'item' => __('That product is not available in the shop.'),
            ]);
        }

        $snapshot = MerchandisePersonalisation::snapshotAnswers(
            $item->personalisationFieldDefinitions(),
            $personalisationAnswers,
        );
        $fingerprint = MerchandisePersonalisation::fingerprint($snapshot);

        return DB::transaction(function () use ($team, $user, $item, $variant, $quantity, $snapshot, $fingerprint): MerchandiseBasketLine {
            $existing = MerchandiseBasketLine::query()
                ->forTeamUser($team, $user)
                ->where('merchandise_item_variant_id', $variant->getKey())
                ->get()
                ->first(function (MerchandiseBasketLine $line) use ($fingerprint): bool {
                    return MerchandisePersonalisation::fingerprint($line->personalisationSnapshot()) === $fingerprint;
                });

            if ($existing instanceof MerchandiseBasketLine) {
                $existing->update([
                    'quantity' => $existing->quantity + $quantity,
                ]);

                return $existing->refresh();
            }

            return MerchandiseBasketLine::query()->create([
                'team_id' => $team->getKey(),
                'user_id' => $user->getKey(),
                'merchandise_item_id' => $item->getKey(),
                'merchandise_item_variant_id' => $variant->getKey(),
                'quantity' => $quantity,
                'personalisation' => $snapshot === [] ? null : $snapshot,
            ]);
        });
    }
}
