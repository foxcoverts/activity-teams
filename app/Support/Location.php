<?php

namespace App\Support;

use Symfony\Component\Intl\Countries;

class Location
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $street,
        public readonly ?string $street2,
        public readonly ?string $city,
        public readonly ?string $region,
        public readonly ?string $postcode,
        public readonly ?string $country,
    ) {}

    public function addressArray(): array
    {
        return array_filter([
            'street' => $this->street,
            'street2' => $this->street2,
            'city' => $this->city,
            'region' => $this->region,
            'postcode' => $this->postcode,
            'country_name' => $this->countryName(),
        ]);
    }

    public function countryName(): ?string
    {
        return $this->country ? Countries::getName($this->country) : null;
    }
}
