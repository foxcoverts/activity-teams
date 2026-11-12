<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasLabel;

enum TentativeTriage: string implements HasLabel
{
    case NeedsLead = 'needs_lead';
    case ReadyToConfirm = 'ready_to_confirm';
    case Unstaffed = 'unstaffed';
    case AtRisk = 'at_risk';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsLead => __('Needs lead'),
            self::ReadyToConfirm => __('Ready to confirm'),
            self::Unstaffed => __('Unstaffed'),
            self::AtRisk => __('At risk'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function color(): array
    {
        return match ($this) {
            self::ReadyToConfirm => Palette::positive(),
            self::NeedsLead, self::AtRisk, self::Unstaffed => Palette::uncertain(),
        };
    }

    public function severity(): AttentionSeverity
    {
        return match ($this) {
            self::NeedsLead, self::AtRisk, self::Unstaffed => AttentionSeverity::Uncertain,
            self::ReadyToConfirm => AttentionSeverity::Positive,
        };
    }

    public function countsTowardBadge(): bool
    {
        return true;
    }
}
