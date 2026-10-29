<?php

namespace Tests\Unit\Support;

use App\Support\Coords;
use Tests\TestCase;

class CoordsTest extends TestCase
{
    public function test_try_from_returns_null_unless_both_coordinates_are_present(): void
    {
        $this->assertNull(Coords::tryFrom(null, null));
        $this->assertNull(Coords::tryFrom(51.5074, null));
        $this->assertNull(Coords::tryFrom(null, -0.1278));
    }

    public function test_try_from_returns_coords_when_both_values_are_set(): void
    {
        $coords = Coords::tryFrom(51.5074, -0.1278);

        $this->assertNotNull($coords);
        $this->assertEqualsWithDelta(51.5074, $coords->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.1278, $coords->longitude, 0.0001);
    }
}
