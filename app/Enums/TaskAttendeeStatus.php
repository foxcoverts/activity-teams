<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskAttendeeStatus: string implements HasColor, HasIcon, HasLabel
{
    case NeedsAction = 'needs-action';
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
    case InProcess = 'in-process';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsAction => __('Awaiting response'),
            self::Accepted => __('Accepted'),
            self::Tentative => __('Maybe'),
            self::Declined => __('Declined'),
            self::InProcess => __('In process'),
            self::Completed => __('Completed'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Accepted, self::Completed => Palette::positive(),
            self::Declined => Palette::destructive(),
            self::Tentative, self::InProcess => Palette::uncertain(),
            self::NeedsAction => Palette::neutral(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Accepted => Heroicon::CheckCircle,
            self::Tentative => Heroicon::QuestionMarkCircle,
            self::Declined => Heroicon::XCircle,
            self::InProcess => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::CheckCircle,
            self::NeedsAction => Heroicon::OutlinedClock,
        };
    }

    public function responseSentence(): string
    {
        return match ($this) {
            self::Accepted => __('You accepted this task.'),
            self::Tentative => __('You said maybe.'),
            self::Declined => __('You declined this task.'),
            self::InProcess => __('You marked this task in process.'),
            self::Completed => __('You marked this task completed.'),
            self::NeedsAction => '',
        };
    }

    public function toPartstat(): string
    {
        return strtoupper($this->value);
    }

    public static function tryFromPartstat(?string $partstat): ?self
    {
        return match (strtoupper((string) $partstat)) {
            'NEEDS-ACTION' => self::NeedsAction,
            'ACCEPTED' => self::Accepted,
            'TENTATIVE' => self::Tentative,
            'DECLINED' => self::Declined,
            'IN-PROCESS' => self::InProcess,
            'COMPLETED' => self::Completed,
            default => null,
        };
    }

    /**
     * PARTSTATs that belong on the personal rota feed.
     *
     * @return list<self>
     */
    public static function onRota(): array
    {
        return [self::Accepted, self::Tentative, self::InProcess];
    }

    public function isOnRota(): bool
    {
        return in_array($this, self::onRota(), true);
    }

    /**
     * Open assignment PARTSTATs for the member Tasks list and dashboard (invites plus accepted work).
     *
     * @return list<self>
     */
    public static function activeAssignments(): array
    {
        return [self::NeedsAction, self::Accepted, self::Tentative, self::InProcess];
    }
}
