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
        'submitted_at',
        'cancelled_at',
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

    public function total(): float
    {
        return (float) $this->lines->sum(
            fn (MerchandiseOrderLine $line): float => $line->lineTotal(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MerchandiseOrderStatus::class,
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
