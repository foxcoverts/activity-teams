<?php

namespace App\Filament\Bookings;

use App\Enums\KeyHolderAttendance;
use App\Models\Booking;
use App\Support\BookingPeople;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class BookingKeysAction
{
    public static function make(): Action
    {
        return Action::make('keys')
            ->label(__('Keys'))
            ->modalHeading(__('Keys'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->visible(fn (Booking $record): bool => $record->requiredLocks()->isNotEmpty())
            ->icon(fn (Booking $record): Heroicon => self::attendance($record)->getIcon())
            ->color(fn (Booking $record): array => self::attendance($record)->getColor())
            ->tooltip(fn (Booking $record): string => self::attendance($record)->getLabel())
            ->modalContent(fn (Booking $record): View => view('filament.bookings.keys-host', [
                'booking' => $record,
            ]));
    }

    private static function attendance(Booking $booking): KeyHolderAttendance
    {
        return BookingPeople::holderAttendance($booking);
    }
}
