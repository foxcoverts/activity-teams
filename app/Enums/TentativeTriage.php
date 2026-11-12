<?php

namespace App\Enums;

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

    public function severity(): AttentionSeverity
    {
        return match ($this) {
            self::NeedsLead, self::AtRisk, self::Unstaffed => AttentionSeverity::Uncertain,
            self::ReadyToConfirm => AttentionSeverity::Neutral,
        };
    }

    public function countsTowardBadge(): bool
    {
        return $this !== self::ReadyToConfirm;
    }
}
