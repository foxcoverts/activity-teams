<?php

namespace App\Models;

use Database\Factories\MerchandiseItemVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseItemVariant extends Model
{
    /** @use HasFactory<MerchandiseItemVariantFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_id',
        'label',
        'sort',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function issuedMerchandise(): HasMany
    {
        return $this->hasMany(IssuedMerchandise::class);
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
