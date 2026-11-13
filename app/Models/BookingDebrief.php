<?php

namespace App\Models;

use Database\Factories\BookingDebriefFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingDebrief extends Model
{
    /** @use HasFactory<BookingDebriefFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'booking_id',
        'hours',
        'participant_count',
        'weather',
        'notes',
        'completed_at',
        'completed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'participant_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function equipmentSets(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentSet::class);
    }
}
