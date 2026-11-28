<?php

namespace App\Models;

use Database\Factories\MerchandiseSupplierOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseSupplierOrder extends Model
{
    /** @use HasFactory<MerchandiseSupplierOrderFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'merchandise_supplier_id',
        'notes',
        'sent_at',
        'open_draft_key',
    ];

    public static function draftKeyFor(Team $team, MerchandiseSupplier $supplier): string
    {
        return $team->getKey().':'.$supplier->getKey();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MerchandiseSupplier::class, 'merchandise_supplier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MerchandiseOrderLine::class);
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->whereNull('sent_at');
    }

    public function scopeSent(Builder $query): void
    {
        $query->whereNotNull('sent_at');
    }

    public function isDraft(): bool
    {
        return $this->sent_at === null;
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public static function draftFor(Team $team, MerchandiseSupplier $supplier): ?self
    {
        return static::query()
            ->whereBelongsTo($team)
            ->whereBelongsTo($supplier, 'supplier')
            ->draft()
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
