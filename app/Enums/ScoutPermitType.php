<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ScoutPermitType: string implements HasColor, HasIcon, HasLabel
{
    use CoversValues;

    case Personal = 'personal';
    case Leadership = 'leadership';
    case Supervisory = 'supervisory';

    public function getLabel(): string
    {
        return match ($this) {
            self::Personal => __('Personal'),
            self::Leadership => __('Leadership'),
            self::Supervisory => __('Supervisory'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Personal => Palette::neutral(),
            self::Leadership => Palette::positive(),
            self::Supervisory => Palette::manager(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Personal => Heroicon::User,
            self::Leadership => Heroicon::UserGroup,
            self::Supervisory => Heroicon::ShieldCheck,
        };
    }
}
