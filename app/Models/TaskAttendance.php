<?php

namespace App\Models;

use App\Enums\TaskAttendeeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaskAttendance extends Model
{
    protected $table = 'task_user';

    protected $primaryKey = 'task_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'task_id',
        'user_id',
        'status',
        'comment',
        'token',
        'calendar_imip_at',
    ];

    protected $attributes = [
        'status' => TaskAttendeeStatus::NeedsAction->value,
        'comment' => null,
    ];

    protected static function booted(): void
    {
        static::saving(function (TaskAttendance $attendance): void {
            if (blank($attendance->token)) {
                $attendance->token = static::generateToken();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TaskAttendeeStatus::class,
            'calendar_imip_at' => 'datetime',
        ];
    }

    public function hasCalendarChannel(): bool
    {
        return $this->calendar_imip_at !== null;
    }

    public static function generateToken(): string
    {
        return hash('sha256', sprintf(
            '%s%s%s',
            config('app.token_prefix', ''),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        ));
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function needsAction(): bool
    {
        return $this->status === TaskAttendeeStatus::NeedsAction;
    }

    public function isDeclined(): bool
    {
        return $this->status === TaskAttendeeStatus::Declined;
    }
}
