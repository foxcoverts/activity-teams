<?php

namespace App\Models;

use App\Enums\TentativeTriage;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerAttentionNotification extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'booking_id',
        'triage',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'triage' => TentativeTriage::class,
            'notified_at' => 'datetime',
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
}
