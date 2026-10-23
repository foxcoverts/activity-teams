<?php

namespace App\Support;

use Carbon\CarbonTimeZone;

class Timezones
{
    public static function groups(): array
    {
        return collect(CarbonTimeZone::listIdentifiers())
            ->mapWithKeys(fn ($identifier) => [$identifier => self::formatTimezone($identifier)])
            ->groupBy(fn (string $label, string $identifier): string => str($identifier)->before('/'), preserveKeys: true)
            ->toArray();
    }

    protected static function formatTimezone(string $identifier): string
    {
        $tz = new CarbonTimeZone($identifier);

        return __(':city (:offset)', [
            'city' => str($tz)->after('/')->replace('_', ' ')->replace('/', ' - '),
            'offset' => $tz->toOffsetName(),
        ]);
    }
}
