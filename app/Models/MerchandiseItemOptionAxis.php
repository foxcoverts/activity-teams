<?php

namespace App\Models;

use Database\Factories\MerchandiseItemOptionAxisFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseItemOptionAxis extends Model
{
    /** @use HasFactory<MerchandiseItemOptionAxisFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_id',
        'name',
        'sort',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(MerchandiseItemOptionValue::class)->orderBy('sort')->orderBy('label');
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
