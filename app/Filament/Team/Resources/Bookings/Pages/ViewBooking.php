<?php

namespace App\Filament\Team\Resources\Bookings\Pages;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Team\Resources\Bookings\BookingResource;
use App\Support\LeadAttendanceWarning;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('respond')
                ->authorize(fn (): bool => auth()->user()?->can('respond', [$this->record, auth()->user()]) ?? false)
                ->schema([
                    ToggleButtons::make('response')
                        ->label(__('Status'))
                        ->options(BookingAttendeeResponse::class)
                        ->inline()
                        ->default(fn (): ?string => BookingAttendeeResponse::tryFromStatus($this->currentResponse())?->value)
                        ->live()
                        ->helperText(function (Get $get): ?string {
                            $user = auth()->user();

                            if ($user === null || ! $this->getRecord()->isLead($user)) {
                                return null;
                            }

                            $choice = $get('response');

                            return LeadAttendanceWarning::message(
                                $this->getRecord(),
                                $choice instanceof BookingAttendeeResponse
                                    ? $choice->toStatus()
                                    : BookingAttendeeResponse::tryFrom((string) $choice)?->toStatus(),
                            );
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $response = $data['response'] ?? null;
                    $response = $response instanceof BookingAttendeeResponse
                        ? $response
                        : BookingAttendeeResponse::tryFrom((string) $response);

                    if ($response === null) {
                        return;
                    }

                    try {
                        (new RespondToBookingAction($this->record))(
                            auth()->user(),
                            $response->toStatus(),
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title(__('Attendance updated'))->send();
                }),
        ];
    }

    private function currentResponse(): ?BookingAttendeeStatus
    {
        $status = $this->getRecord()->attendees->find(auth()->user())?->attendance?->status;

        if ($status === null || $status === BookingAttendeeStatus::NeedsAction) {
            return null;
        }

        return $status;
    }
}
