<?php

namespace App\Models;

use App\Enums\MerchandiseOrderStatus;
use Database\Factories\MerchandiseOrderLineFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'merchandise_supplier_order_id',
        'arrived_at',
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

    public function supplierOrder(): BelongsTo
    {
        return $this->belongsTo(MerchandiseSupplierOrder::class, 'merchandise_supplier_order_id');
    }

    /**
     * Candidate lines for a supplier draft board (checkbox in/out).
     *
     * Includes lines on this supplier's current draft and lines not yet on any
     * supplier order. Excludes lines on sent supplier orders. US-87 will also
     * exclude stock-fulfilled lines via {@see scopeNotFulfilledFromStock()}.
     */
    public function scopeBoardCandidatesForSupplier(Builder $query, Team $team, MerchandiseSupplier $supplier): void
    {
        $draftId = MerchandiseSupplierOrder::draftFor($team, $supplier)?->getKey();

        $query
            ->whereHas(
                'order',
                fn (Builder $orders): Builder => $orders
                    ->whereBelongsTo($team)
                    ->where('status', MerchandiseOrderStatus::Approved),
            )
            ->whereHas(
                'item',
                fn (Builder $items): Builder => $items
                    ->whereBelongsTo($team)
                    ->where('merchandise_supplier_id', $supplier->getKey()),
            )
            ->notFulfilledFromStock()
            ->where(function (Builder $lines) use ($draftId): void {
                $lines->whereNull('merchandise_supplier_order_id');

                if (filled($draftId)) {
                    $lines->orWhere('merchandise_supplier_order_id', $draftId);
                }
            });
    }

    /**
     * Extension point for US-87: exclude lines already picked / handed over from stock.
     * No-op until fulfilment columns exist.
     */
    public function scopeNotFulfilledFromStock(Builder $query): void
    {
        //
    }

    /**
     * Whether this line still belongs on the supplier-order board for ordering.
     * US-87 will return false once the line is fulfilled from stock.
     */
    public function needsSupplierOrdering(): bool
    {
        return true;
    }

    public function isOnSentSupplierOrder(): bool
    {
        $this->loadMissing('supplierOrder');

        return $this->supplierOrder instanceof MerchandiseSupplierOrder
            && $this->supplierOrder->isSent();
    }

    public function isArrived(): bool
    {
        return $this->arrived_at !== null;
    }

    public function scopeArrived(Builder $query): void
    {
        $query->whereNotNull('arrived_at');
    }

    public function scopeNotArrived(Builder $query): void
    {
        $query->whereNull('arrived_at');
    }

    /**
     * Future amend cutoff: lines on a sent supplier order cannot be amended.
     */
    public function canBeAmended(): bool
    {
        return ! $this->isOnSentSupplierOrder();
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
            'arrived_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'approved_unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'personalisation' => 'array',
            'sort' => 'integer',
        ];
    }
}
