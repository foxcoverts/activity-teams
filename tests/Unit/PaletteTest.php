<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Support\Palette;
use Filament\Support\Colors\Color;
use PHPUnit\Framework\TestCase;

class PaletteTest extends TestCase
{
    public function test_meanings_use_one_shade_each(): void
    {
        $this->assertSame(Color::Blue, Palette::informative());
        $this->assertSame(Color::Green, Palette::positive());
        $this->assertSame(Color::Red, Palette::destructive());
        $this->assertSame(Color::Amber, Palette::uncertain());
        $this->assertSame(Color::Gray, Palette::neutral());
    }

    public function test_a_tentative_booking_uses_the_uncertain_colour(): void
    {
        $this->assertSame(Palette::uncertain(), BookingStatus::Tentative->getColor());
        $this->assertSame(Palette::positive(), BookingStatus::Confirmed->getColor());
        $this->assertSame(Palette::destructive(), BookingStatus::Cancelled->getColor());
    }
}
