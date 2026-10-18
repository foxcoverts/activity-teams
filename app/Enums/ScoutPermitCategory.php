<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ScoutPermitCategory: string implements HasIcon, HasLabel
{
    use CoversValues;

    case ArtificialTopRope = 'Artificial Top Rope';
    case NaturalTopRope = 'Natural Top Rope';
    case ArtificialLeadClimbing = 'Artificial Lead Climbing';
    case NaturalLeadClimbing = 'Natural Lead Climbing';

    public function getLabel(): string
    {
        return match ($this) {
            self::ArtificialTopRope => __('Artificial top rope'),
            self::NaturalTopRope => __('Natural top rope'),
            self::ArtificialLeadClimbing => __('Artificial lead climbing'),
            self::NaturalLeadClimbing => __('Natural lead climbing'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ArtificialTopRope => Heroicon::BuildingOffice,
            self::NaturalTopRope => Heroicon::Sun,
            self::ArtificialLeadClimbing => Heroicon::BuildingOffice2,
            self::NaturalLeadClimbing => Heroicon::Map,
        };
    }

    /**
     * @return list<static>
     */
    protected function directlyCovers(): array
    {
        return match ($this) {
            self::NaturalTopRope, self::ArtificialLeadClimbing => [self::ArtificialTopRope],
            self::NaturalLeadClimbing => [self::NaturalTopRope, self::ArtificialLeadClimbing],
            self::ArtificialTopRope => [],
        };
    }
}
