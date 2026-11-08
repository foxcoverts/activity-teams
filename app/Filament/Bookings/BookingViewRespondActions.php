<?php

namespace App\Filament\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

class BookingViewRespondActions
{
    public static function make(): ActionGroup
    {
        return ActionGroup::make(BookingResponseChoices::actions())
            ->label(fn (mixed $livewire): string => static::status($livewire)->getLabel())
            ->icon(fn (mixed $livewire): Heroicon => static::status($livewire)->getIcon())
            ->color(fn (mixed $livewire): array => static::status($livewire)->getColor())
            ->button();
    }

    private static function status(mixed $livewire): BookingAttendeeStatus
    {
        $record = is_object($livewire) && method_exists($livewire, 'getRecord')
            ? $livewire->getRecord()
            : null;

        return $record instanceof Booking
            ? BookingResponseChoices::currentStatus($record)
            : BookingAttendeeStatus::NeedsAction;
    }
}
