<?php

namespace App\Filament\Team\Resources\Bookings\Pages;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Team\Resources\Bookings\BookingResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('respond')
                ->visible(fn (): bool => auth()->user()?->can('respond', [$this->record, auth()->user()]) ?? false)
                ->schema([
                    Select::make('status')
                        ->options(BookingAttendeeStatus::class)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        (new RespondToBookingAction($this->record))(
                            auth()->user(),
                            $data['status'],
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('Attendance updated'))->send();
                }),
        ];
    }
}
