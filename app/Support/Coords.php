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

    public function distanceTo(self $other): float
    {
        $latitudeDifference = deg2rad($other->latitude - $this->latitude);
        $longitudeDifference = deg2rad($other->longitude - $this->longitude);

        $haversine = (sin($latitudeDifference / 2) ** 2)
            + (cos(deg2rad($this->latitude))
                * cos(deg2rad($other->latitude))
                * (sin($longitudeDifference / 2) ** 2));
        $centralAngle = 2 * atan2(sqrt(min(1, $haversine)), sqrt(1 - min(1, $haversine)));

        return 6_371_000 * $centralAngle;
    }
}
