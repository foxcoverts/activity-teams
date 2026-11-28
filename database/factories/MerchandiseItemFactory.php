<?php

namespace Database\Factories;

use App\Enums\MerchandiseAvailability;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemImage;
use App\Models\MerchandiseItemVariant;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItem>
 */
class MerchandiseItemFactory extends Factory
{
    protected $model = MerchandiseItem::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'availability' => MerchandiseAvailability::Available,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'availability' => MerchandiseAvailability::Draft,
        ]);
    }

    public function mothballed(): static
    {
        return $this->state(fn (): array => [
            'availability' => MerchandiseAvailability::Mothballed,
        ]);
    }

    public function withImage(?string $path = null): static
    {
        return $this->afterCreating(function (MerchandiseItem $item) use ($path): void {
            MerchandiseItemImage::factory()->for($item, 'item')->create([
                'path' => $path ?? 'merchandise/'.$item->team_id.'/'.fake()->uuid().'.jpg',
                'sort' => 0,
            ]);
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (MerchandiseItem $item): void {
            if ($item->variants()->exists()) {
                return;
            }

            $variantAvailability = match ($item->availability) {
                MerchandiseAvailability::Mothballed => MerchandiseAvailability::Mothballed,
                MerchandiseAvailability::Draft => MerchandiseAvailability::Draft,
                default => MerchandiseAvailability::Available,
            };

            MerchandiseItemVariant::factory()->for($item, 'item')->create([
                'label' => 'One size',
                'price' => 0,
                'availability' => $variantAvailability,
                'sort' => 0,
            ]);
        });
    }
}
