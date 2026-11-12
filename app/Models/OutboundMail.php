<?php

namespace App\Models;

use App\Enums\OutboundMailStatus;
use Database\Factories\OutboundMailFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutboundMail extends Model
{
    /** @use HasFactory<OutboundMailFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'message_id',
        'recipient',
        'user_id',
        'status',
        'mailable',
    ];

    protected function casts(): array
    {
        return [
            'status' => OutboundMailStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bounces(): HasMany
    {
        return $this->hasMany(MailBounce::class);
    }

    public static function normalizeMessageId(?string $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }

        $normalized = strtolower(trim($messageId, " \t\n\r\0\x0B<>"));

        return $normalized === '' ? null : $normalized;
    }
}
