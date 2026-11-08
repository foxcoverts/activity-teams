<?php

namespace App\Filament\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

class DashboardRespondActions
{
    public static function make(?Booking $booking = null, mixed $livewire = null): ActionGroup
    {
        $status = $booking !== null
            ? BookingResponseChoices::currentStatus($booking)
            : BookingAttendeeStatus::NeedsAction;

        $arguments = $booking !== null
            ? ['booking' => (string) $booking->getKey()]
            : [];

        $actions = array_map(
            function (Action $action) use ($arguments, $booking, $livewire): Action {
                if ($arguments !== []) {
                    $action = $action($arguments);
                }

                if ($booking !== null) {
                    $action->record($booking);
                }

                if ($livewire !== null) {
                    $action->livewire($livewire);
                }

                return $action;
            },
            BookingResponseChoices::actions(),
        );

        $group = ActionGroup::make($actions)
            ->label(static::triggerLabel($status))
            ->color(static::triggerColor($status))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->button()
            ->dropdownTeleport();

        if ($booking !== null) {
            $group->record($booking);
        }

        if ($livewire !== null) {
            $group->livewire($livewire);
        }

        return $group;
    }

    public static function triggerLabel(BookingAttendeeStatus $status): string
    {
        return $status === BookingAttendeeStatus::Tentative
            ? __('Confirm')
            : __('Respond');
    }

    /**
     * @return string|array<int, string>
     */
    public static function triggerColor(BookingAttendeeStatus $status): string|array
    {
        return $status === BookingAttendeeStatus::Tentative
            ? Palette::uncertain()
            : 'primary';
    }
}
