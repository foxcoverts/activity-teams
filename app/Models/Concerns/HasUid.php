<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUid
{
    public function getUidAttribute(): string
    {
        $domain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'activity-teams.local';
        if ($domain === 'localhost') {
            $domain = 'activity-teams.local';
        }

        return sprintf('%s+%s@%s', Str::singular($this->getTable()), $this->id, $domain);
    }

    public static function findByUid(string $uid): ?static
    {
        $parts = preg_split('/[+@]/', $uid);

        if (($parts[0] ?? null) !== Str::singular((new static)->getTable())) {
            return null;
        }

        return static::find($parts[1] ?? null);
    }
}
