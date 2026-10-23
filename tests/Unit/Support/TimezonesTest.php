<?php

namespace Tests\Unit\Support;

use App\Support\Timezones;
use Carbon\Carbon;
use Tests\TestCase;

class TimezonesTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_no_slash_zones_are_grouped_correctly(): void
    {
        $groups = Timezones::groups();

        $this->assertArrayHasKey('UTC', $groups);
        $this->assertArrayHasKey('UTC', $groups['UTC']);
    }

    public function test_groups_by_region_and_keeps_the_identifier_as_the_value(): void
    {
        $groups = Timezones::groups();

        $this->assertArrayHasKey('Europe/London', $groups['Europe']);
        $this->assertArrayHasKey('America', $groups);
    }

    public function test_multi_part_zones_read_naturally(): void
    {
        $groups = Timezones::groups();

        $this->assertStringStartsWith('Argentina - Buenos Aires', $groups['America']['America/Argentina/Buenos_Aires']);
        $this->assertStringStartsWith('North Dakota - Center', $groups['America']['America/North_Dakota/Center']);
    }

    public function test_the_offset_follows_daylight_saving(): void
    {
        Carbon::setTestNow('2026-01-15');
        $this->assertStringEndsWith('(+00:00)', Timezones::groups()['Europe']['Europe/London']);

        Carbon::setTestNow('2026-07-15');
        $this->assertStringEndsWith('(+01:00)', Timezones::groups()['Europe']['Europe/London']);
    }

    public function test_every_identifier_is_valid(): void
    {
        $groups = Timezones::groups();

        foreach ($groups as $region => $timezones) {
            foreach ($timezones as $identifier => $label) {
                $this->assertTrue(
                    in_array($identifier, \DateTimeZone::listIdentifiers()),
                    "Identifier {$identifier} is not valid"
                );
            }
        }
    }
}
