<?php

namespace App\Qualifications;

use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class PermitType
{
    public static function label(string $id): string
    {
        return match ($id) {
            'personal' => __('Personal'),
            'leadership' => __('Leadership'),
            'supervisory' => __('Supervisory'),
            default => $id,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function color(string $id): array
    {
        return match ($id) {
            'personal' => Palette::neutral(),
            'leadership' => Palette::positive(),
            'supervisory' => Palette::manager(),
            default => Palette::neutral(),
        };
    }

    public static function icon(string $id): Heroicon
    {
        return match ($id) {
            'personal' => Heroicon::User,
            'leadership' => Heroicon::UserGroup,
            'supervisory' => Heroicon::ShieldCheck,
            default => Heroicon::QuestionMarkCircle,
        };
    }
}
