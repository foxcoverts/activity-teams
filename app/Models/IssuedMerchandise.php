<?php

namespace App\Models;

use App\Enums\IssuedMerchandiseSource;
use Database\Factories\IssuedMerchandiseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class IssuedMerchandise extends Model
{
    /** @use HasFactory<IssuedMerchandiseFactory> */
    use HasFactory, HasUlids;

    protected $table = 'issued_merchandise';

    protected $fillable = [
        'team_id',
        'user_id',
        'merchandise_item_variant_id',
        'issued_on',
        'note',
        'source',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemVariant::class, 'merchandise_item_variant_id');
    }

    public function item(): HasOneThrough
    {
        return $this->hasOneThrough(
            MerchandiseItem::class,
            MerchandiseItemVariant::class,
            'id',
            'id',
            'merchandise_item_variant_id',
            'merchandise_item_id',
        );
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'source' => IssuedMerchandiseSource::class,
        ];
    }
}
