<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Models\Team;
use App\Models\User;
use App\Support\ManagerAttention;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Throwable;

/**
 * A manager dashboard section. Loads its own data, isolates failures, and stays tenant-scoped.
 */
abstract class ManagerSection extends Widget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    abstract protected function sectionData(): array;

    /**
     * Booking triage widgets stay hidden when the viewer cannot manage the team
     * (BookingPolicy grants booking access through manages, not system admin alone).
     */
    public static function canViewBookingTriage(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User
            && $team instanceof Team
            && $user->manages($team);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            return ['failed' => false, ...$this->sectionData()];
        } catch (Throwable $e) {
            report($e);

            return ['failed' => true];
        }
    }

    protected function attention(): ManagerAttention
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return ManagerAttention::for($team);
    }

    protected function team(): Team
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return $team;
    }
}
