<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Section: string implements HasColor, HasLabel
{
    case Squirrel = 'squirrel';
    case Beaver = 'beaver';
    case Cub = 'cub';
    case Scout = 'scout';
    case Explorer = 'explorer';
    case Network = 'network';
    case Adult = 'adult';
    case Parent = 'parent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Squirrel => __('Squirrel'),
            self::Beaver => __('Beaver'),
            self::Cub => __('Cub'),
            self::Scout => __('Scout'),
            self::Explorer => __('Explorer'),
            self::Network => __('Network'),
            self::Adult => __('Adult'),
            self::Parent => __('Parent'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Squirrel, self::Beaver, self::Cub, self::Scout, self::Explorer, self::Network => Palette::youth(),
            self::Adult => Palette::adult(),
            self::Parent => Palette::neutral(),
        };
    }
}
