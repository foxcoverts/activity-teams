<?php

namespace App\Models;

use Database\Factories\MerchandiseBasketLineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseBasketLine extends Model
{
    /** @use HasFactory<MerchandiseBasketLineFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'user_id',
        'merchandise_item_id',
        'merchandise_item_variant_id',
        'quantity',
        'personalisation',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItemVariant::class, 'merchandise_item_variant_id');
    }

    public function scopeForTeamUser(Builder $query, Team $team, User $user): void
    {
        $query->whereBelongsTo($team)->whereBelongsTo($user);
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
        $price = (float) ($this->variant?->price ?? 0);

        return $price * $this->quantity;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'personalisation' => 'array',
        ];
    }
}
