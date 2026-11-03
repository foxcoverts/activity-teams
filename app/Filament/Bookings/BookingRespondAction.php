<?php

namespace App\Filament\Bookings;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\LeadAttendanceWarning;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use InvalidArgumentException;

class BookingRespondAction
{
    /**
     * The member's own response to a booking. Used by the booking view and the member dashboard so both share one flow.
     *
     * @param  array<int, mixed>  $leadingComponents
     */
    public static function make(array $leadingComponents = []): Action
    {
        return Action::make('respond')
            ->authorize(fn (Booking $record): bool => auth()->user()?->can('respond', [$record, auth()->user()]) ?? false)
            ->schema([
                ...$leadingComponents,
                ToggleButtons::make('response')
                    ->label(__('Status'))
                    ->options(BookingAttendeeResponse::class)
                    ->inline()
                    ->default(fn (Booking $record): ?string => BookingAttendeeResponse::tryFromStatus(self::currentResponse($record))?->value)
                    ->live()
                    ->helperText(function (Get $get, Booking $record): ?string {
                        $user = auth()->user();

                        if ($user === null || ! $record->isLead($user)) {
                            return null;
                        }

                        $choice = $get('response');

                        return LeadAttendanceWarning::message(
                            $record,
                            $choice instanceof BookingAttendeeResponse
                                ? $choice->toStatus()
                                : BookingAttendeeResponse::tryFrom((string) $choice)?->toStatus(),
                        );
                    })
                    ->required(),
            ])
            ->action(function (array $data, Booking $record, Action $action): void {
                $response = $data['response'] ?? null;
                $response = $response instanceof BookingAttendeeResponse
                    ? $response
                    : BookingAttendeeResponse::tryFrom((string) $response);

                if ($response === null) {
                    return;
                }

                try {
                    (new RespondToBookingAction($record))(
                        auth()->user(),
                        $response->toStatus(),
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('Attendance updated'))->send();

                $record->unsetRelation('attendees');
                $action->getLivewire()->dispatch('booking-attendance-updated');
            });
    }

    private static function currentResponse(Booking $record): ?BookingAttendeeStatus
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $status = $record->attendees->find($user)?->attendance?->status;

        if ($status === null || $status === BookingAttendeeStatus::NeedsAction) {
            return null;
        }

        return $status;
    }
}
