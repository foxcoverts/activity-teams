<?php

namespace App\Models;

use App\Enums\MerchandiseAvailability;
use Database\Factories\MerchandiseItemVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class MerchandiseItemVariant extends Model
{
    /** @use HasFactory<MerchandiseItemVariantFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_id',
        'label',
        'price',
        'availability',
        'sort',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MerchandiseItemVariant $variant): void {
            if ($variant->issuedMerchandise()->exists()) {
                throw ValidationException::withMessages([
                    'variants' => __('This variant cannot be deleted because it appears on the issued log. Mothball it instead.'),
                ]);
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function issuedMerchandise(): HasMany
    {
        return $this->hasMany(IssuedMerchandise::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('availability', MerchandiseAvailability::Available);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'availability' => MerchandiseAvailability::class,
            'sort' => 'integer',
        ];
    }
}
