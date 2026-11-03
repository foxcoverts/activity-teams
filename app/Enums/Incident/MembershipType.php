<?php

namespace App\Enums\Incident;

use Filament\Support\Contracts\HasLabel;

enum MembershipType: string implements HasLabel
{
    case SquirrelScout = 'Squirrel Scout';
    case BeaverScout = 'Beaver Scout';
    case CubScout = 'Cub Scout';
    case Scout = 'Scout';
    case ExplorerScout = 'Explorer Scout';
    case ScoutNetwork = 'Scout Network';
    case AdultVolunteer = 'Adult volunteer (not Scout Network)';
    case NonUkScout = 'Non UK Scout (any age)';
    case OrganisedGroupYoungPerson = 'Organised Group Young Person';
    case OrganisedGroupAdult = 'Organised Group Adult';
    case YouthPublic = 'Young member of public';
    case AdultPublic = 'Adult member of public';

    public function getLabel(): string
    {
        return __($this->value);
    }

    public function group(): string
    {
        return match ($this) {
            self::SquirrelScout,
            self::BeaverScout,
            self::CubScout,
            self::Scout,
            self::ExplorerScout,
            self::ScoutNetwork,
            self::AdultVolunteer => __('UK Scout'),
            self::NonUkScout => __('Non UK Scout'),
            self::OrganisedGroupYoungPerson,
            self::OrganisedGroupAdult => __('Organised Group (not Scouts)'),
            self::YouthPublic,
            self::AdultPublic => __('Public'),
        };
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (self::cases() as $membershipType) {
            $groups[$membershipType->group()][$membershipType->value] = $membershipType->getLabel();
        }

        return $groups;
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::YouthPublic, self::AdultPublic], true);
    }
}
