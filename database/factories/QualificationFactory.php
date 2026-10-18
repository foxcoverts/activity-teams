<?php

namespace Database\Factories;

use App\Enums\GirlguidingLevel;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Models\GirlguidingQualification;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Qualification>
 */
class QualificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Qualification $qualification): void {
            if (filled($qualification->detail_id)) {
                return;
            }

            $qualification->detail()->associate(ScoutPermit::factory()->create());
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function scoutPermit(array $attributes = []): static
    {
        return $this->withDetail(fn (): Model => ScoutPermit::factory()->create($attributes));
    }

    public function archery(): static
    {
        return $this->scoutPermit([
            'activity' => ScoutPermitActivity::Archery,
            'category' => null,
        ]);
    }

    public function mountainTraining(MountainTrainingAward $award = MountainTrainingAward::ClimbingWallInstructor): static
    {
        return $this->withDetail(
            fn (): Model => MountainTrainingQualification::factory()->create(['award' => $award]),
            expiresOn: null,
        );
    }

    public function girlguiding(GirlguidingLevel $level = GirlguidingLevel::One): static
    {
        return $this->withDetail(
            fn (): Model => GirlguidingQualification::factory()->create(['level' => $level]),
        );
    }

    public function forMember(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * @param  callable(): Model  $makeDetail
     */
    protected function withDetail(callable $makeDetail, mixed $expiresOn = 'keep'): static
    {
        return $this->state(function () use ($makeDetail, $expiresOn): array {
            $detail = $makeDetail();

            $attributes = [
                'detail_type' => $detail->getMorphClass(),
                'detail_id' => $detail->getKey(),
            ];

            if ($expiresOn !== 'keep') {
                $attributes['expires_on'] = $expiresOn;
            }

            return $attributes;
        });
    }
}
