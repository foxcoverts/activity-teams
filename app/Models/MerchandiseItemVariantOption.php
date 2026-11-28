<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MerchandiseItemVariantOption extends Pivot
{
    use HasUlids;

    protected $table = 'merchandise_item_variant_options';

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemVariant::class, 'merchandise_item_variant_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemOptionValue::class, 'merchandise_item_option_value_id');
    }
}
