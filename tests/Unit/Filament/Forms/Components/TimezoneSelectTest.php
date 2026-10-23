<?php

namespace Tests\Unit\Filament\Forms\Components;

use App\Filament\Forms\Components\TimezoneSelect;
use Carbon\CarbonTimeZone;
use Tests\TestCase;

class TimezoneSelectTest extends TestCase
{
    public function test_options_include_every_timezone_grouped_by_region_with_its_current_offset(): void
    {
        $this->travelTo('2026-01-15 12:00:00 UTC');

        $options = TimezoneSelect::make('timezone')->getOptions();
        $timezones = collect($options)->flatMap(fn (array $regionOptions): array => array_keys($regionOptions))
            ->sort()
            ->values()
            ->all();
        $expectedTimezones = CarbonTimeZone::listIdentifiers();
        sort($expectedTimezones);

        $this->assertSame($expectedTimezones, $timezones);
        $this->assertSame('London (+00:00)', $options['Europe']['Europe/London']);
        $this->assertSame('New York (-05:00)', $options['America']['America/New_York']);
        $this->assertSame('UTC (+00:00)', $options['UTC']['UTC']);
    }

    public function test_offset_labels_reflect_daylight_saving_time(): void
    {
        $this->travelTo('2026-07-15 12:00:00 UTC');

        $options = TimezoneSelect::make('timezone')->getOptions();

        $this->assertSame('London (+01:00)', $options['Europe']['Europe/London']);
        $this->assertSame('New York (-04:00)', $options['America']['America/New_York']);
    }
}
