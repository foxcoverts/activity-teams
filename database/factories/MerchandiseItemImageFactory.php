<?php

namespace Database\Factories;

use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchandiseItemImage>
 */
class MerchandiseItemImageFactory extends Factory
{
    protected $model = MerchandiseItemImage::class;

    public function definition(): array
    {
        return [
            'merchandise_item_id' => MerchandiseItem::factory(),
            'path' => 'merchandise/testing/'.fake()->uuid().'.jpg',
            'thumb_path' => null,
            'sort' => 0,
        ];
    }
}
