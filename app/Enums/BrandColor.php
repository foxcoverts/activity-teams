<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BrandColor: string implements HasColor, HasLabel
{
    case Blue = 'blue';
    case Emerald = 'emerald';
    case Rose = 'rose';
    case Amber = 'amber';
    case Violet = 'violet';
    case Sky = 'sky';
    case Teal = 'teal';
    case Orange = 'orange';
    case Indigo = 'indigo';
    case Fuchsia = 'fuchsia';

    public function getLabel(): string
    {
        return match ($this) {
            self::Blue => __('Blue'),
            self::Emerald => __('Emerald'),
            self::Rose => __('Rose'),
            self::Amber => __('Amber'),
            self::Violet => __('Violet'),
            self::Sky => __('Sky'),
            self::Teal => __('Teal'),
            self::Orange => __('Orange'),
            self::Indigo => __('Indigo'),
            self::Fuchsia => __('Fuchsia'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Blue => Color::Blue,
            self::Emerald => Color::Emerald,
            self::Rose => Color::Rose,
            self::Amber => Color::Amber,
            self::Violet => Color::Violet,
            self::Sky => Color::Sky,
            self::Teal => Color::Teal,
            self::Orange => Color::Orange,
            self::Indigo => Color::Indigo,
            self::Fuchsia => Color::Fuchsia,
        };
    }

    /**
     * Hex for ui-avatars (no leading #). Defaults to shade 400 so icons stay softer than badge mid-tones.
     */
    public function hex(int $shade = 400): string
    {
        $palette = $this->getColor();
        $color = $palette[$shade] ?? $palette[400] ?? $palette[500];

        return ltrim(Color::convertToHex($color), '#');
    }

    public static function fromSeed(string $seed): self
    {
        $cases = self::cases();
        $index = abs(crc32($seed)) % count($cases);

        return $cases[$index];
    }
}
