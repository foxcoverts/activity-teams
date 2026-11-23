<?php

namespace App\Models;

use App\Enums\MerchandiseAvailability;
use Database\Factories\MerchandiseItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseItem extends Model
{
    /** @use HasFactory<MerchandiseItemFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'title',
        'description',
        'availability',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MerchandiseItemVariant::class)->orderBy('sort')->orderBy('label');
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->whereBelongsTo($team);
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
            'availability' => MerchandiseAvailability::class,
        ];
    }
}
