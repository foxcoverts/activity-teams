<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\DebriefsDueWidget as MemberDebriefsDueWidget;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;

class DebriefsDueWidget extends MemberDebriefsDueWidget
{
    use ScopedToTeam;

    /**
     * @return Collection<int, Booking>
     */
    protected function outstanding(User $user): Collection
    {
        $team = $this->dashboard()->team;

        return parent::outstanding($user)
            ->when(
                $team !== null,
                fn (Collection $bookings): Collection => $bookings
                    ->where('team_id', $team->getKey())
                    ->values(),
            );
    }
}
