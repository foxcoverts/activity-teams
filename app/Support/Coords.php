<?php

namespace App\Support;

class Coords
{
    public const DEFAULT_MAP_LATITUDE = 52.6362;

    public const DEFAULT_MAP_LONGITUDE = -1.1331969;

    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude
    ) {}

    public static function tryFrom(?float $latitude, ?float $longitude): ?static
    {
        if (is_null($latitude) || is_null($longitude)) {
            return null;
        }

        return new static($latitude, $longitude);
    }
}
