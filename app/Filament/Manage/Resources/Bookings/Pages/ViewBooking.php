<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\BookingStatusActions;
use App\Filament\Manage\Resources\Bookings\BookingTimeline;
use App\Models\Booking;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getInfolistContentComponent(),
                $this->getRelationManagersContentComponent(),
                EmbeddedSchema::make('timeline'),
            ]);
    }

    public function timeline(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
                BookingTimeline::section(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        if ($record instanceof Booking && $record->isCancelled()) {
            return [
                BookingKeysAction::make(),
                BookingStatusActions::restore()->button(),
                DeleteAction::make(),
            ];
        }

        return [
            BookingKeysAction::make(),
            EditAction::make(),
            BookingStatusActions::group(),
            DeleteAction::make(),
        ];
    }
}
