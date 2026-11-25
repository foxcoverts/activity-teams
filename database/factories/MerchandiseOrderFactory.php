<?php

namespace Database\Factories;

use App\Enums\MerchandiseOrderStatus;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseOrderLine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseOrder>
 */
class MerchandiseOrderFactory extends Factory
{
    protected $model = MerchandiseOrder::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory()->withShop(),
            'user_id' => User::factory(),
            'status' => MerchandiseOrderStatus::Submitted,
            'submitted_at' => now(),
            'cancelled_at' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => MerchandiseOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function withLine(?array $overrides = null): static
    {
        return $this->afterCreating(function (MerchandiseOrder $order) use ($overrides): void {
            MerchandiseOrderLine::factory()->for($order, 'order')->create($overrides ?? []);
        });
    }
}
