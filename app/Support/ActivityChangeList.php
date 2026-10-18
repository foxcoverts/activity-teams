<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ActivityChangeList
{
    public static function summary(mixed $properties): string
    {
        if ($properties instanceof Collection) {
            $properties = $properties->toArray();
        }

        if (! is_array($properties) || $properties === []) {
            return '—';
        }

        if (isset($properties['attributes']) || isset($properties['old'])) {
            $lines = [];

            foreach ($properties['attributes'] ?? [] as $key => $value) {
                $lines[] = $key.': '.self::display($properties['old'][$key] ?? null).' → '.self::display($value);
            }

            return $lines === [] ? '—' : implode(', ', $lines);
        }

        return collect($properties)
            ->map(fn (mixed $value, mixed $key): string => $key.': '.self::display($value))
            ->join(', ');
    }

    private static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: '—';
    }
}
