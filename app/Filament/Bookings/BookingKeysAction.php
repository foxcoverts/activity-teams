<?php

namespace App\Filament\Bookings;

use App\Models\Booking;
use App\Models\Key;
use Filament\Actions\Action;
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
            ->modalContent(fn (Booking $record): View => view('filament.bookings.keys', [
                'lines' => self::lines($record),
            ]));
    }

    /**
     * @return list<array{name: string, rings: list<string>}>
     */
    public static function lines(Booking $booking): array
    {
        return $booking->requiredKeys()
            ->map(fn (Key $key): array => [
                'name' => $key->name,
                'rings' => $key->accessRingDescriptions(),
            ])
            ->all();
    }
}
