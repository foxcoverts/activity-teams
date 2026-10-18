<?php

namespace App\Casts;

use Carbon\CarbonTimeZone;
use DateTimeZone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AsTimezone implements CastsAttributes, SerializesCastableAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonTimeZone
    {
        if (is_null($value)) {
            return $value;
        }
        if ($value instanceof CarbonTimeZone) {
            return $value;
        }

        if (is_array($value) && is_string($value['timezone'] ?? null)) {
            $value = $value['timezone'];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded) && is_string($decoded['timezone'] ?? null)) {
                $value = $decoded['timezone'];
            }
        }

        return CarbonTimeZone::instance($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return $value;
        }
        if (is_string($value)) {
            $value = new CarbonTimeZone($value);
        }
        if ($value instanceof DateTimeZone) {
            return $value->getName();
        }

        throw new InvalidArgumentException('The given value is not a DateTimeZone instance.');
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return $value;
        }
        if ($value instanceof DateTimeZone) {
            return $value->getName();
        }

        throw new InvalidArgumentException('The given value is not a DateTimeZone instance.');
    }
}
