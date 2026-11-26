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
        'approved_unit_price',
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

    public function listUnitPrice(): float
    {
        return (float) $this->unit_price;
    }

    public function approvedUnitPrice(): float
    {
        return $this->approved_unit_price !== null
            ? (float) $this->approved_unit_price
            : $this->listUnitPrice();
    }

    public function hasUnitDiscount(): bool
    {
        return round($this->approvedUnitPrice(), 2) < round($this->listUnitPrice(), 2);
    }

    public function unitDiscountAmount(): float
    {
        return max(0, round($this->listUnitPrice() - $this->approvedUnitPrice(), 2));
    }

    public function listLineTotal(): float
    {
        return round($this->listUnitPrice() * $this->quantity, 2);
    }

    public function lineTotal(): float
    {
        return round($this->approvedUnitPrice() * $this->quantity, 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'approved_unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'personalisation' => 'array',
            'sort' => 'integer',
        ];
    }
}
