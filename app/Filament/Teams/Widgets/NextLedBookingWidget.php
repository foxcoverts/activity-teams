<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\NextLedBookingWidget as MemberNextLedBookingWidget;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;

class NextLedBookingWidget extends MemberNextLedBookingWidget
{
    use ScopedToTeam;
}
