<?php

namespace Database\Factories;

use App\Enums\IssuedMerchandiseSource;
use App\Models\IssuedMerchandise;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssuedMerchandise>
 */
class IssuedMerchandiseFactory extends Factory
{
    protected $model = IssuedMerchandise::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'merchandise_item_variant_id' => function (array $attributes): string {
                $item = MerchandiseItem::factory()->create([
                    'team_id' => $attributes['team_id'],
                ]);

                return $item->variants()->firstOrFail()->getKey();
            },
            'issued_on' => fake()->date(),
            'note' => fake()->optional()->sentence(),
            'source' => IssuedMerchandiseSource::Gift,
        ];
    }

    public function forTeam(Team $team): static
    {
        return $this->state(fn (): array => [
            'team_id' => $team->getKey(),
        ]);
    }

    public function forVariant(MerchandiseItemVariant $variant): static
    {
        return $this->state(function () use ($variant): array {
            $variant->loadMissing('item');

            return [
                'merchandise_item_variant_id' => $variant->getKey(),
                'team_id' => $variant->item?->team_id,
            ];
        });
    }

    public function forItem(MerchandiseItem $item): static
    {
        return $this->state(function () use ($item): array {
            $variant = $item->variants()->first()
                ?? MerchandiseItemVariant::factory()->for($item, 'item')->create();

            return [
                'merchandise_item_variant_id' => $variant->getKey(),
                'team_id' => $item->team_id,
            ];
        });
    }
}
