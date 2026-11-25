<?php

namespace App\Models;

use Database\Factories\MerchandiseOrderLineFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseOrderLine extends Model
{
    /** @use HasFactory<MerchandiseOrderLineFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_order_id',
        'merchandise_item_id',
        'merchandise_item_variant_id',
        'item_title',
        'variant_label',
        'unit_price',
        'quantity',
        'personalisation',
        'sort',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MerchandiseOrder::class, 'merchandise_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemVariant::class, 'merchandise_item_variant_id');
    }

    /**
     * @return list<array{key: string, label: string, value: string}>
     */
    public function personalisationSnapshot(): array
    {
        $value = $this->personalisation;

        return is_array($value) ? array_values($value) : [];
    }

    public function lineTotal(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'personalisation' => 'array',
            'sort' => 'integer',
        ];
    }
}
