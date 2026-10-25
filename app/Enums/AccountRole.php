<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AccountRole: string implements HasColor, HasIcon, HasLabel
{
    case Oversight = 'oversight';
    case SystemAdmin = 'system-admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Oversight => __('Oversight'),
            self::SystemAdmin => __('System admin'),
        };
    }

    public function getIcon(): Heroicon|string
    {
        return match ($this) {
            self::Oversight => Heroicon::Eye,
            self::SystemAdmin => Heroicon::ShieldCheck,
        };
    }

    public function getColor(): array
    {
        return Palette::neutral();
    }
}
