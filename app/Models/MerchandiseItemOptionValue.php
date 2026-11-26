<?php

namespace App\Models;

use Database\Factories\MerchandiseItemOptionValueFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MerchandiseItemOptionValue extends Model
{
    /** @use HasFactory<MerchandiseItemOptionValueFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_option_axis_id',
        'label',
        'sort',
    ];

    public function axis(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemOptionAxis::class, 'merchandise_item_option_axis_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            MerchandiseItemVariant::class,
            'merchandise_item_variant_options',
        )->using(MerchandiseItemVariantOption::class)->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
