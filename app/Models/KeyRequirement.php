<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class KeyRequirement extends Model
{
    use HasUlids, LogsActivity;

    protected $fillable = [
        'id',
        'key_id',
        'activity_id',
        'venue_id',
        'created_at',
        'updated_at',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function key(): BelongsTo
    {
        return $this->belongsTo(Key::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function summary(): string
    {
        $this->loadMissing('activity', 'venue');

        return __(':activity at :venue', [
            'activity' => $this->activity?->name,
            'venue' => $this->venue?->name,
        ]);
    }
}
