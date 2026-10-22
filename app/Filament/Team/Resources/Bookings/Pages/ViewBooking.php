<?php

namespace App\Filament\Team\Resources\Bookings\Pages;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Team\Resources\Bookings\BookingResource;
use App\Filament\Team\Resources\Bookings\BookingTimeline;
use App\Livewire\SessionRoster;
use App\Models\Booking;
use App\Models\User;
use App\Support\LeadAttendanceWarning;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use InvalidArgumentException;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    public function content(Schema $schema): Schema
    {
        $components = [];

        $record = $this->getRecord();
        $user = auth()->user();

        if ($record instanceof Booking && $user instanceof User) {
            $record->loadMissing('team');

            if ($record->team !== null && $user->isCurrentMemberOf($record->team)) {
                $components[] = Livewire::make(SessionRoster::class, ['booking' => $record])
                    ->columnSpan(['default' => 1, 'lg' => 2]);
            }
        }

        array_unshift(
            $components,
            $this->getInfolistContentComponent()
                ->columnSpan(['default' => 1, 'lg' => 3]),
        );

        $components[] = EmbeddedSchema::make('timeline')
            ->columnSpan(['default' => 1, 'lg' => 3]);

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 5])
                ->schema($components),
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
        $actions = [];
        $record = $this->getRecord();
        $user = auth()->user();

        if ($record instanceof Booking && $user instanceof User && $record->isLead($user)) {
            $actions[] = BookingKeysAction::make();
        }

        $actions[] = Action::make('respond')
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
            });

        return $actions;
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
