<?php

namespace App\Models;

use Database\Factories\TeamInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(TeamInvitationFactory::class)]
class TeamInvitation extends Model
{
    use HasFactory, HasUlids;

    public const VALID_DAYS = 14;

    protected $fillable = [
        'team_id',
        'user_id',
        'email',
        'token',
        'sent_at',
        'expires_at',
        'declined_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'declined_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The placeholder person this invitation will be claimed for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDeclined(): bool
    {
        return $this->declined_at !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isDeclined() && ! $this->isRevoked() && ! $this->isExpired();
    }
}
