<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum KeyRingSituation: string implements HasColor, HasIcon, HasLabel
{
    case Attending = 'attending';
    case Invited = 'invited';
    case Cupboard = 'cupboard';
    case NotInvited = 'not_invited';
    case CannotMakeIt = 'cannot_make_it';

    /**
     * @return list<self>
     */
    public static function displayOrder(): array
    {
        return [
            self::Attending,
            self::Invited,
            self::Cupboard,
            self::NotInvited,
            self::CannotMakeIt,
        ];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Attending => __('Attending'),
            self::Invited => __('Invited'),
            self::Cupboard => __('In the cupboard'),
            self::NotInvited => __('Not invited'),
            self::CannotMakeIt => __('Cannot make it'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Attending => Palette::positive(),
            self::Invited, self::Cupboard => Palette::neutral(),
            self::NotInvited => Palette::uncertain(),
            self::CannotMakeIt => Palette::destructive(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Attending => Heroicon::CheckCircle,
            self::Invited => Heroicon::QuestionMarkCircle,
            self::Cupboard => Heroicon::OutlinedArchiveBox,
            self::NotInvited => Heroicon::OutlinedUserPlus,
            self::CannotMakeIt => Heroicon::XCircle,
        };
    }

    public function helper(): ?string
    {
        return $this === self::Cupboard
            ? __('These just need passing to an attendee.')
            : null;
    }
}
