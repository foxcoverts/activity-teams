<?php

namespace App\Filament\Member\Resources\Bookings\Pages;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Member\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'rota' => Tab::make(__('Rota'))
                ->modifyQueryUsing(fn (Builder $query) => $query->future()->notCancelled()->attendeeStatus($user, [
                    BookingAttendeeStatus::Accepted,
                    BookingAttendeeStatus::Tentative,
                ])),
            'invites' => Tab::make(__('Invites'))
                ->modifyQueryUsing(fn (Builder $query) => $query->future()->notCancelled()->attendeeStatus($user, BookingAttendeeStatus::outstanding())),
        ];
    }
}
