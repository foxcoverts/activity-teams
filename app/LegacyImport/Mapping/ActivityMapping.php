<?php

namespace App\LegacyImport\Mapping;

final class ActivityMapping
{
    public const DROP = 'drop';

    public const OTHER = 'other';

    public const CLIMBING = 'Climbing';

    public const CLIMBING_ACCESS = 'Climbing Access';

    public const AUDIENCE_SCOUTS = 'Scouts';

    public const AUDIENCE_GIRLGUIDES = 'Girlguides';

    public const AUDIENCE_TEAM = 'Team';

    /**
     * @return array{
     *     mode: string,
     *     activity?: string,
     *     audience?: string,
     *     activity_label?: string|null,
     *     requires_lead_instructor?: bool,
     *     venue?: string,
     *     private_venue?: bool
     * }|null
     */
    public static function for(string $legacyActivity, ?string $groupName = null, ?string $location = null): ?array
    {
        $activity = trim($legacyActivity);
        $group = trim((string) $groupName);

        // Legacy sandbox rows used activity and/or group_name "Testing".
        if ($activity === 'Testing' || $group === 'Testing') {
            return ['mode' => self::DROP];
        }

        $venue = self::venueFor($location);
        $girlguidingGroup = self::groupLooksGirlguiding($groupName);

        return match ($activity) {
            'Climbing' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING,
                'audience' => $girlguidingGroup ? self::AUDIENCE_GIRLGUIDES : self::AUDIENCE_SCOUTS,
                'activity_label' => null,
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Guides Climbing' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING,
                'audience' => self::AUDIENCE_GIRLGUIDES,
                'activity_label' => 'Guides Climbing',
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Maintenance' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING,
                'audience' => self::AUDIENCE_TEAM,
                'activity_label' => 'Maintenance',
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Climbing Maintenance' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING,
                'audience' => self::AUDIENCE_TEAM,
                'activity_label' => 'Climbing Maintenance',
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Tower Inspection' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING_ACCESS,
                'audience' => self::AUDIENCE_TEAM,
                'activity_label' => 'Tower Inspection',
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Kit Checks' => [
                'mode' => 'catalog',
                'activity' => self::CLIMBING_ACCESS,
                'audience' => self::AUDIENCE_TEAM,
                'activity_label' => 'Kit Checks',
                'requires_lead_instructor' => true,
                ...$venue,
            ],
            'Splash Camp' => [
                'mode' => self::OTHER,
                'activity_label' => 'Splash Camp',
                'requires_lead_instructor' => false,
                ...$venue,
            ],
            'Wake up the Woods' => [
                'mode' => self::OTHER,
                'activity_label' => 'Wake up the Woods',
                'requires_lead_instructor' => false,
                ...$venue,
            ],
            'District AGM' => [
                'mode' => self::OTHER,
                'activity_label' => 'District AGM',
                'requires_lead_instructor' => false,
                'venue' => null,
                'private_venue' => true,
                'private_venue_name' => $location ?: 'Mary Forryan Centre, Leicester Road, Hinckley. LE10 1HD',
            ],
            default => null,
        };
    }

    /**
     * @return array{venue: string|null, private_venue: bool}
     */
    private static function venueFor(?string $location): array
    {
        $location = trim((string) $location);

        return match ($location) {
            'Fox Coverts Campsite', '' => [
                'venue' => 'Fox Coverts Campsite',
                'private_venue' => false,
            ],
            'Leicester Outdoor Pursuits Centre' => [
                'venue' => 'Leicester Outdoor Pursuits Centre',
                'private_venue' => false,
            ],
            default => [
                'venue' => str_contains(strtolower($location), 'mary forryan')
                    ? null
                    : 'Fox Coverts Campsite',
                'private_venue' => str_contains(strtolower($location), 'mary forryan'),
                'private_venue_name' => str_contains(strtolower($location), 'mary forryan') ? $location : null,
            ],
        };
    }

    public static function groupLooksGirlguiding(?string $groupName): bool
    {
        $group = strtolower((string) $groupName);

        return (bool) preg_match('/\b(guides?|girlguiding|brownies?|rangers?)\b/', $group);
    }

    public static function lockForKeyName(string $name): string
    {
        return str_contains(strtolower($name), 'warden')
            ? 'Campsite Warden'
            : 'Campsite Climbing';
    }
}
