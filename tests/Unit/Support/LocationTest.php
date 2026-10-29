<?php

namespace Tests\Unit\Support;

use App\Support\Location;
use Tests\TestCase;

class LocationTest extends TestCase
{
    public function test_address_array_omits_blank_parts_and_names_the_country(): void
    {
        $location = new Location(
            name: 'Hall',
            street: 'Newbold Road',
            street2: null,
            city: 'Kirkby Mallory',
            region: '',
            postcode: 'LE9 7QG',
            country: 'GB',
        );

        $this->assertSame([
            'street' => 'Newbold Road',
            'city' => 'Kirkby Mallory',
            'postcode' => 'LE9 7QG',
            'country_name' => 'United Kingdom',
        ], $location->addressArray());
    }

    public function test_address_array_uses_the_localized_name_for_a_non_gb_country(): void
    {
        $location = new Location(
            name: 'Hall',
            street: '1 Rue de la Paix',
            street2: null,
            city: 'Paris',
            region: null,
            postcode: null,
            country: 'FR',
        );

        $this->assertSame([
            'street' => '1 Rue de la Paix',
            'city' => 'Paris',
            'country_name' => 'France',
        ], $location->addressArray());
        $this->assertSame('France', $location->countryName());
    }

    public function test_address_array_is_empty_when_no_postal_parts_are_set(): void
    {
        $location = new Location(
            name: 'Hall',
            street: null,
            street2: null,
            city: null,
            region: null,
            postcode: null,
            country: null,
        );

        $this->assertSame([], $location->addressArray());
        $this->assertNull($location->countryName());
    }
}
