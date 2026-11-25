<?php

namespace Database\Factories;

use App\Models\MerchandiseBasketLine;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseBasketLine>
 */
class MerchandiseBasketLineFactory extends Factory
{
    protected $model = MerchandiseBasketLine::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory()->withShop(),
            'user_id' => User::factory(),
            'merchandise_item_id' => MerchandiseItem::factory(),
            'merchandise_item_variant_id' => MerchandiseItemVariant::factory(),
            'quantity' => 1,
            'personalisation' => null,
        ];
    }

    public function forTeamUser(Team $team, User $user, ?MerchandiseItem $item = null): static
    {
        $item ??= MerchandiseItem::factory()->for($team)->withImage()->create();
        $variant = $item->variants()->firstOrFail();

        return $this->state(fn (): array => [
            'team_id' => $team->getKey(),
            'user_id' => $user->getKey(),
            'merchandise_item_id' => $item->getKey(),
            'merchandise_item_variant_id' => $variant->getKey(),
        ]);
    }
}
