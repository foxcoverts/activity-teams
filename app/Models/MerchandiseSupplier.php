<?php

namespace App\Models;

use Database\Factories\MerchandiseSupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class MerchandiseSupplier extends Model
{
    /** @use HasFactory<MerchandiseSupplierFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'name',
        'notes',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MerchandiseSupplier $supplier): void {
            if ($supplier->items()->exists()) {
                throw ValidationException::withMessages([
                    'supplier' => __('This supplier cannot be deleted while catalogue items still use it. Clear the supplier on those items first, or rename it (for example with “(DNU)”).'),
                ]);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MerchandiseItem::class);
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
    }
}
