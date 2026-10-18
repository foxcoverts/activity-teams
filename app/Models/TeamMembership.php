<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeamMembership extends Pivot
{
    use HasUlids;

    public $incrementing = true;

    protected $table = 'team_user';

    protected $fillable = [
        'team_id',
        'user_id',
        'role',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'left_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->left_at === null;
    }
}
