<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Bookings\RefreshesWhenAttendanceChanges;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\BookingStatusActions;
use App\Filament\Manage\Resources\Bookings\BookingTimeline;
use App\Models\Booking;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ViewBooking extends ViewRecord
{
    use RefreshesWhenAttendanceChanges;

    protected static string $resource = BookingResource::class;

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 5])
                    ->schema([
                        $this->getInfolistContentComponent()
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                        $this->getRelationManagersContentComponent()
                            ->columnSpan(['default' => 1, 'lg' => 2]),
                        EmbeddedSchema::make('timeline')
                            ->columnSpan(['default' => 1, 'lg' => 3]),
                    ]),
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
            BookingResource::editAction(),
            BookingStatusActions::group(),
            DeleteAction::make(),
        ];
    }
}
