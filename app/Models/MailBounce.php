<?php

namespace App\Models;

use Database\Factories\MailBounceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailBounce extends Model
{
    /** @use HasFactory<MailBounceFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'outbound_mail_id',
        'provider',
        'provider_message_id',
        'category',
        'action',
        'message',
        'hard',
        'bounced_at',
    ];

    protected function casts(): array
    {
        return [
            'hard' => 'boolean',
            'bounced_at' => 'datetime',
        ];
    }

    public function outboundMail(): BelongsTo
    {
        return $this->belongsTo(OutboundMail::class);
    }
}
