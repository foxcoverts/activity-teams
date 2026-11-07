<?php

namespace Tests\Unit;

use App\Enums\BrandColor;
use Filament\Support\Colors\Color;
use PHPUnit\Framework\TestCase;

class BrandColorTest extends TestCase
{
    public function test_from_seed_is_deterministic(): void
    {
        $this->assertSame(BrandColor::fromSeed('Archery'), BrandColor::fromSeed('Archery'));
        $this->assertSame(BrandColor::fromSeed(''), BrandColor::fromSeed(''));
    }

    public function test_from_seed_picks_a_case(): void
    {
        $this->assertContains(BrandColor::fromSeed('Climbing'), BrandColor::cases());
    }

    public function test_hex_returns_a_six_digit_value_without_a_hash(): void
    {
        $hex = BrandColor::Blue->hex();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{6}$/', $hex);
        $this->assertSame(ltrim(Color::convertToHex(Color::Blue[400]), '#'), $hex);
        $this->assertNotSame(ltrim(Color::convertToHex(Color::Blue[500]), '#'), $hex);
    }
}
