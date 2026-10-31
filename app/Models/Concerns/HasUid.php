<?php

namespace App\Models\Concerns;

use App\Support\InboundMailAddress;
use Illuminate\Support\Str;

trait HasUid
{
    public function getUidAttribute(): string
    {
        return sprintf('%s+%s@%s', Str::singular($this->getTable()), $this->id, InboundMailAddress::domain());
    }

    public static function findByUid(string $uid): ?static
    {
        $uid = Str::after(strtolower(trim($uid)), 'mailto:');
        $parts = preg_split('/[+@]/', $uid);

        if (($parts[0] ?? null) !== Str::singular((new static)->getTable())) {
            return null;
        }

        return static::find($parts[1] ?? null);
    }
}
