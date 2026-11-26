<?php

namespace App\Models;

use App\Enums\MerchandiseOrderStatus;
use Database\Factories\MerchandiseOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseOrder extends Model
{
    /** @use HasFactory<MerchandiseOrderFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'user_id',
        'status',
        'order_discount',
        'rejection_reason',
        'approval_note',
        'submitted_at',
        'cancelled_at',
        'approved_at',
        'rejected_at',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MerchandiseOrderLine::class)->orderBy('sort');
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
    }

    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    public function canBeCancelled(): bool
    {
        return $this->status === MerchandiseOrderStatus::Submitted;
    }

    public function canBeReviewed(): bool
    {
        return $this->status === MerchandiseOrderStatus::Submitted;
    }

    public function itemsSubtotal(): float
    {
        return round((float) $this->lines->sum(
            fn (MerchandiseOrderLine $line): float => $line->lineTotal(),
        ), 2);
    }

    public function total(): float
    {
        return max(0, round($this->itemsSubtotal() - (float) $this->order_discount, 2));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchandiseOrderStatus::class,
            'order_discount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }
}
