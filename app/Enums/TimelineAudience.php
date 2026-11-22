<?php

namespace App\Enums;

enum TimelineAudience: string
{
    case Manager = 'manager';
    case Member = 'member';

    public function isManager(): bool
    {
        return $this === self::Manager;
    }

    public function isMember(): bool
    {
        return $this === self::Member;
    }
}
