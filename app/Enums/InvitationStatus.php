<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum InvitationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case Invited = 'invited';
    case Expired = 'expired';
    case Declined = 'declined';
    case NotInvited = 'not_invited';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Invited => __('Invited'),
            self::Expired => __('Invitation expired'),
            self::Declined => __('Declined'),
            self::NotInvited => __('Not invited'),
        };
    }

    /**
     * @return array<int|string, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Active => Palette::positive(),
            self::Invited => Palette::neutral(),
            self::Expired => Palette::uncertain(),
            self::Declined => Palette::destructive(),
            self::NotInvited => Palette::neutral(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Active => Heroicon::CheckCircle,
            self::Invited => Heroicon::Envelope,
            self::Expired => Heroicon::Clock,
            self::Declined => Heroicon::XCircle,
            self::NotInvited => Heroicon::MinusCircle,
        };
    }

    public function isUnresolved(): bool
    {
        return $this !== self::Active;
    }
}
