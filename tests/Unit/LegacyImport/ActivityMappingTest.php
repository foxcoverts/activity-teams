<?php

namespace Tests\Unit\LegacyImport;

use App\Enums\MembershipRole;
use App\LegacyImport\Mapping\ActivityMapping;
use App\LegacyImport\Mapping\NewsBodyMapping;
use App\LegacyImport\Mapping\QualificationMapping;
use App\LegacyImport\Mapping\UserMapping;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivityMappingTest extends TestCase
{
    public function test_testing_bookings_are_dropped(): void
    {
        $this->assertSame(
            ActivityMapping::DROP,
            ActivityMapping::for('Testing', 'Fox Coverts Climbing Team', 'Fox Coverts Campsite')['mode'],
        );

        $this->assertSame(
            ActivityMapping::DROP,
            ActivityMapping::for('Climbing', 'Testing', 'Fox Coverts Campsite')['mode'],
        );
    }

    public function test_maintenance_maps_to_climbing_team_with_label(): void
    {
        $mapping = ActivityMapping::for('Maintenance', 'Fox Coverts Climbing Team', 'Fox Coverts Campsite');

        $this->assertSame('catalog', $mapping['mode']);
        $this->assertSame('Climbing', $mapping['activity']);
        $this->assertSame('Team', $mapping['audience']);
        $this->assertSame('Maintenance', $mapping['activity_label']);
        $this->assertTrue($mapping['requires_lead_instructor']);
    }

    public function test_tower_inspection_maps_to_climbing_access(): void
    {
        $mapping = ActivityMapping::for('Tower Inspection', 'Fox Coverts Climbing Team', 'Fox Coverts Campsite');

        $this->assertSame('Climbing Access', $mapping['activity']);
        $this->assertSame('Team', $mapping['audience']);
        $this->assertSame('Tower Inspection', $mapping['activity_label']);
    }

    public function test_splash_camp_is_other_without_lead(): void
    {
        $mapping = ActivityMapping::for('Splash Camp', 'Leicestershire Scouts', 'Leicester Outdoor Pursuits Centre');

        $this->assertSame(ActivityMapping::OTHER, $mapping['mode']);
        $this->assertFalse($mapping['requires_lead_instructor']);
        $this->assertSame('Leicester Outdoor Pursuits Centre', $mapping['venue']);
    }

    public function test_guides_group_on_climbing_selects_girlguides(): void
    {
        $mapping = ActivityMapping::for('Climbing', '3rd Wollaton Brownies & Guides', 'Fox Coverts Campsite');

        $this->assertSame('Girlguides', $mapping['audience']);
    }

    #[DataProvider('wardenKeyProvider')]
    public function test_key_names_map_to_locks(string $name, string $lock): void
    {
        $this->assertSame($lock, ActivityMapping::lockForKeyName($name));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function wardenKeyProvider(): array
    {
        return [
            'warden' => ['Phil’s Warden Key', 'Campsite Warden'],
            'climbing' => ["Phill's Key", 'Campsite Climbing'],
        ];
    }

    public function test_team_leader_becomes_system_admin_and_manager(): void
    {
        $mapped = UserMapping::attributes((object) [
            'role' => 'team-leader',
            'accreditations' => '',
            'password' => 'hash',
        ]);

        $this->assertTrue($mapped['is_system_admin']);
        $this->assertSame(MembershipRole::Manager, $mapped['membership_role']);
    }

    public function test_legacy_suspended_is_imported_unsuspended_without_team_membership(): void
    {
        $mapped = UserMapping::attributes((object) [
            'role' => 'suspended',
            'accreditations' => '',
            'password' => 'hash',
        ]);

        $this->assertFalse($mapped['suspended']);
        $this->assertFalse(UserMapping::shouldJoinTeam((object) ['role' => 'suspended']));
        $this->assertTrue(UserMapping::shouldJoinTeam((object) ['role' => 'team-member']));
    }

    public function test_news_body_rewrites_rota_and_invite_links(): void
    {
        $body = 'See your [rota](/rota) and [invites](/invite), or [absolute](https://climbfoxcoverts.co.uk/rota). Keep [external](https://example.test/rota).';

        $this->assertSame(
            'See your [rota](/bookings?tab=rota) and [invites](/bookings?tab=invites), or [absolute](/bookings?tab=rota). Keep [external](https://example.test/rota).',
            NewsBodyMapping::rewrite($body),
        );
    }

    public function test_girlguiding_detail_maps_scheme_and_level(): void
    {
        $mapped = QualificationMapping::fromDetail(
            'App\\Models\\GirlguidingQualification',
            (object) ['scheme' => 'climbing', 'level' => 1],
            '2025-01-01',
        );

        $this->assertSame('girlguiding', $mapped['kind']);
        $this->assertSame(['scheme' => 'Climbing', 'level' => '1'], $mapped['payload']);
    }

    public function test_mountain_training_expands_award_codes(): void
    {
        $mapped = QualificationMapping::fromDetail(
            'App\\Models\\MountainTrainingQualification',
            (object) ['award' => 'CWI'],
            null,
        );

        $this->assertSame('Climbing Wall Instructor', $mapped['payload']['award']);
    }
}
