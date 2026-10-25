<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AccountStatus: string implements HasColor, HasIcon, HasLabel
{
    case Deleted = 'deleted';
    case Suspended = 'suspended';
    case Active = 'active';
    case Invited = 'invited';
    case Expired = 'expired';
    case Declined = 'declined';
    case NotInvited = 'not_invited';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Deleted => __('Deleted'),
            self::Suspended => __('Suspended'),
            default => InvitationStatus::tryFrom($this->value)?->getLabel(),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): ?array
    {
        return match ($this) {
            self::Deleted => Palette::neutral(),
            self::Suspended => Palette::destructive(),
            default => InvitationStatus::tryFrom($this->value)?->getColor(),
        };
    }

    public function getIcon(): Heroicon|string|null
    {
        return match ($this) {
            self::Deleted => 'lucide-ghost',
            self::Suspended => Heroicon::NoSymbol,
            default => InvitationStatus::tryFrom($this->value)?->getIcon(),
        };
    }
}
