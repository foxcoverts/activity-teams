<?php

namespace App\Filament\Teams\Resources\Bookings\Pages;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Teams\Resources\Bookings\Actions\CreateAction;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'upcoming' => Tab::make(__('Upcoming'))
                ->modifyQueryUsing(fn (Builder $query) => $query->future()),
            'invites' => Tab::make(__('Invites'))
                ->modifyQueryUsing(fn (Builder $query) => $query->future()->notCancelled()->attendeeStatus($user, BookingAttendeeStatus::outstanding())),
            'rota' => Tab::make(__('My rota'))
                ->modifyQueryUsing(fn (Builder $query) => $query->future()->attendeeStatus($user, [
                    BookingAttendeeStatus::Accepted,
                    BookingAttendeeStatus::Tentative,
                ])),
            'history' => Tab::make(__('History'))
                ->modifyQueryUsing(fn (Builder $query) => $query->past()),
        ];
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->tableSort = null;
    }
}
