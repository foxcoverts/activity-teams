<?php

namespace App\Models;

use Database\Factories\LogbookEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookEntry extends Model
{
    /** @use HasFactory<LogbookEntryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'booking_id',
        'hours',
        'participant_count',
        'weather',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
            'participant_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function applyDebriefFields(BookingDebrief $debrief): void
    {
        $this->hours = $debrief->hours;
        $this->participant_count = $debrief->participant_count;
        $this->weather = $debrief->weather;
    }
}
