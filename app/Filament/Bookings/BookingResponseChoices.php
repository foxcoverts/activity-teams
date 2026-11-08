<?php

namespace App\Filament\Bookings;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use App\Support\LeadAttendanceWarning;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use InvalidArgumentException;

class BookingResponseChoices
{
    /**
     * Response menu actions for the signed-in attendee, omitting their current attendance.
     *
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return array_map(
            fn (BookingAttendeeResponse $response): Action => static::action($response),
            BookingAttendeeResponse::cases(),
        );
    }

    public static function action(BookingAttendeeResponse $response): Action
    {
        return Action::make('respond'.ucfirst($response->value))
            ->label($response->getLabel())
            ->icon($response->getIcon())
            ->color($response->getColor())
            ->visible(fn (Booking $record): bool => static::isAvailable($record, $response))
            ->authorize(fn (Booking $record): bool => auth()->user()?->can('respond', [$record, auth()->user()]) ?? false)
            ->requiresConfirmation(fn (Booking $record): bool => static::needsLeadConfirmation($record, $response))
            ->modalHeading(fn (Booking $record): ?string => static::needsLeadConfirmation($record, $response)
                ? __('Step down as lead instructor?')
                : null)
            ->modalDescription(fn (Booking $record): ?string => static::needsLeadConfirmation($record, $response)
                ? LeadAttendanceWarning::message($record, $response->toStatus())
                : null)
            ->action(function (Booking $record, Action $action) use ($response): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    (new RespondToBookingAction($record))(
                        $user,
                        $response->toStatus(),
                    );
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('Attendance updated'))->send();

                $record->refresh()->unsetRelation('attendees');
                $action->getLivewire()->dispatch('booking-attendance-updated');
            });
    }

    /**
     * @return array<int, BookingAttendeeResponse>
     */
    public static function availableFor(?BookingAttendeeStatus $status): array
    {
        $current = BookingAttendeeResponse::tryFromStatus($status);

        return array_values(array_filter(
            BookingAttendeeResponse::cases(),
            fn (BookingAttendeeResponse $response): bool => $response !== $current,
        ));
    }

    public static function currentStatus(Booking $record): BookingAttendeeStatus
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return BookingAttendeeStatus::NeedsAction;
        }

        return $record->attendees->find($user)?->attendance?->status
            ?? BookingAttendeeStatus::NeedsAction;
    }

    private static function isAvailable(Booking $record, BookingAttendeeResponse $response): bool
    {
        return in_array($response, static::availableFor(static::currentStatus($record)), true);
    }

    private static function needsLeadConfirmation(Booking $record, BookingAttendeeResponse $response): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $record->isLead($user)
            && $response->toStatus() !== BookingAttendeeStatus::Accepted;
    }
}
