<?php

namespace App\Filament\Bookings;

/** display_name is a computed accessor, so Filament must not treat it as a searchable column. */
trait SearchesBookingsGlobally
{
    /**
     * @return array<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'activity_label',
            'activity.name',
            'group_name',
            'audience.name',
        ];
    }
}
