<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commentable_type' => Booking::class,
            'commentable_id' => Booking::factory(),
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }

    public function forBooking(Booking $booking): static
    {
        return $this->for($booking, 'commentable');
    }

    public function trashed(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
