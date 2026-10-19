<?php

namespace App\Models;

use Database\Factories\KeyRingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class KeyRing extends Model
{
    /** @use HasFactory<KeyRingFactory> */
    use HasFactory, HasUlids, LogsActivity;

    protected $fillable = [
        'name',
        'holder_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function holder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'holder_id');
    }

    public function keys(): BelongsToMany
    {
        return $this->belongsToMany(Key::class, 'key_ring_key')->orderBy('keys.name');
    }

    public function holderName(): string
    {
        $this->loadMissing('holder');

        return $this->holder?->name ?? __('In the cupboard');
    }

    /**
     * @return list<string>
     */
    public function keyDescriptions(): array
    {
        $this->loadMissing('keys.covers');

        return $this->keys
            ->map(function (Key $key): string {
                $covers = $key->covers->sortBy('name')->pluck('name');

                if ($covers->isEmpty()) {
                    return $key->name;
                }

                return __(':key, which also opens :covers', [
                    'key' => $key->name,
                    'covers' => $covers->join(', '),
                ]);
            })
            ->values()
            ->all();
    }
}
