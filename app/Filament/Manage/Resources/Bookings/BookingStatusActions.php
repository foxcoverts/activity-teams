<?php

namespace App\Filament\Manage\Resources\Bookings;

use App\Actions\UpdateBookingStatusAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class BookingStatusActions
{
    public static function group(): ActionGroup
    {
        return ActionGroup::make(self::actions())
            ->label(__('Status'))
            ->icon(Heroicon::OutlinedRectangleStack)
            ->button();
    }

    /**
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return [
            self::confirm(),
            self::cancel(),
            self::restore(),
            self::returnToTentative(),
            self::changeLead(),
        ];
    }

    public static function confirm(): Action
    {
        return Action::make('confirmBooking')
            ->label(__('Confirm'))
            ->icon(BookingStatus::Confirmed->getIcon())
            ->color(BookingStatus::Confirmed->getColor())
            ->visible(fn (Booking $record): bool => $record->isTentative())
            ->disabled(fn (Booking $record): bool => self::leadUnavailableReason($record) !== null)
            ->tooltip(fn (Booking $record): ?string => self::leadUnavailableReason($record))
            ->authorize(fn (Booking $record): bool => self::canUpdate($record))
            ->slideOver()
            ->modalWidth(Width::ScreenLarge)
            ->modalHeading(__('Confirm booking'))
            ->fillForm(fn (Booking $record): array => [
                'lead_instructor_id' => self::currentQualifiedLead($record),
                ...self::detailDefaults($record),
            ])
            ->schema([
                Select::make('lead_instructor_id')
                    ->label(__('Lead instructor'))
                    ->options(fn (Booking $record): array => $record->leadOptions())
                    ->required(),
                ...BookingResource::detailComponents(),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                $leadId = (string) $data['lead_instructor_id'];
                unset($data['lead_instructor_id']);

                app(UpdateBookingStatusAction::class)->confirm(
                    $record,
                    self::actor(),
                    $leadId,
                    BookingResource::mergeScheduleData($data),
                );
                self::redirectAfter($record, $action);
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelBooking')
            ->label(__('Cancel'))
            ->icon(BookingStatus::Cancelled->getIcon())
            ->color(BookingStatus::Cancelled->getColor())
            ->visible(fn (Booking $record): bool => $record->isTentative() || $record->isConfirmed())
            ->authorize(fn (Booking $record): bool => self::canUpdate($record))
            ->modalHeading(__('Cancel booking'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->required(),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                app(UpdateBookingStatusAction::class)->cancel($record, self::actor(), (string) $data['reason']);
                self::redirectAfter($record, $action);
            });
    }

    public static function restore(): Action
    {
        return Action::make('restoreBooking')
            ->label(__('Restore'))
            ->icon(BookingStatus::Tentative->getIcon())
            ->color(BookingStatus::Tentative->getColor())
            ->visible(fn (Booking $record): bool => $record->isCancelled())
            ->authorize(fn (Booking $record): bool => self::canUpdate($record))
            ->slideOver()
            ->modalWidth(Width::ScreenLarge)
            ->modalHeading(__('Restore booking'))
            ->modalDescription(__('Make any necessary changes before restoring. The email will include them. Anyone who had not declined will be asked to respond again. An existing decline stands.'))
            ->modalSubmitActionLabel(__('Restore'))
            ->fillForm(fn (Booking $record): array => self::detailDefaults($record))
            ->schema(BookingResource::detailComponents())
            ->action(function (Booking $record, array $data, Action $action): void {
                app(UpdateBookingStatusAction::class)->restore(
                    $record,
                    self::actor(),
                    BookingResource::mergeScheduleData($data),
                );
                self::redirectAfter($record, $action);
            });
    }

    public static function returnToTentative(): Action
    {
        return Action::make('returnToTentative')
            ->label(__('Return to tentative'))
            ->icon(BookingStatus::Tentative->getIcon())
            ->color(BookingStatus::Tentative->getColor())
            ->visible(fn (Booking $record): bool => $record->isConfirmed())
            ->authorize(fn (Booking $record): bool => self::canUpdate($record))
            ->requiresConfirmation()
            ->modalHeading(__('Return to tentative'))
            ->modalDescription(__('This booking will return to tentative.'))
            ->action(function (Booking $record, Action $action): void {
                app(UpdateBookingStatusAction::class)->returnToTentative($record, self::actor());
                self::redirectAfter($record, $action);
            });
    }

    public static function changeLead(): Action
    {
        return Action::make('changeLead')
            ->label(__('Change lead'))
            ->icon(Heroicon::OutlinedUser)
            ->visible(fn (Booking $record): bool => $record->isTentative() || $record->isConfirmed())
            ->disabled(fn (Booking $record): bool => $record->lead_instructor_id === null && self::leadUnavailableReason($record) !== null)
            ->tooltip(fn (Booking $record): ?string => $record->lead_instructor_id === null
                ? self::leadUnavailableReason($record)
                : null)
            ->authorize(fn (Booking $record): bool => self::canUpdate($record))
            ->modalHeading(__('Change lead instructor'))
            ->schema([
                Select::make('lead_instructor_id')
                    ->label(__('Lead instructor'))
                    ->options(function (Booking $record): array {
                        $options = $record->leadOptions();

                        if ($record->lead_instructor_id !== null) {
                            return ['remove' => __('Remove lead instructor')] + $options;
                        }

                        return $options;
                    })
                    ->default(fn (Booking $record): ?string => self::currentQualifiedLead($record))
                    ->live()
                    ->helperText(function (Booking $record, Get $get): ?string {
                        if ($record->isConfirmed() && $get('lead_instructor_id') === 'remove') {
                            return __('Removing the lead instructor will return this booking to tentative.');
                        }

                        return null;
                    })
                    ->required(),
            ])
            ->action(function (Booking $record, array $data, Action $action): void {
                $leadId = $data['lead_instructor_id'] === 'remove' ? null : (string) $data['lead_instructor_id'];
                app(UpdateBookingStatusAction::class)->changeLead($record, self::actor(), $leadId);
                self::redirectAfter($record, $action);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private static function detailDefaults(Booking $record): array
    {
        $timezone = $record->timezone instanceof \DateTimeZone
            ? $record->timezone->getName()
            : (string) $record->timezone;

        return BookingResource::hydrateScheduleData([
            'activity_id' => $record->activity_id,
            'venue_id' => $record->venue_id,
            'start_at' => $record->start_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'end_at' => $record->end_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'timezone' => $timezone,
            'group_name' => $record->group_name,
            'notes' => $record->notes,
            'lead_instructor_notes' => $record->lead_instructor_notes,
        ]);
    }

    private static function currentQualifiedLead(Booking $record): ?string
    {
        $leadId = $record->lead_instructor_id;

        if ($leadId === null || ! array_key_exists($leadId, $record->leadOptions())) {
            return null;
        }

        return $leadId;
    }

    private static function leadUnavailableReason(Booking $record): ?string
    {
        if ($record->acceptedAttendees()->isEmpty()) {
            return __('No one is attending.');
        }

        if ($record->leadOptions() === []) {
            return __('No one attending is qualified to lead this activity.');
        }

        return null;
    }

    private static function canUpdate(Booking $record): bool
    {
        return (bool) auth()->user()?->can('update', $record);
    }

    private static function actor(): User
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw ValidationException::withMessages([
                'status' => __('You cannot change this booking.'),
            ]);
        }

        return $actor;
    }

    private static function redirectAfter(Booking $record, Action $action): void
    {
        $livewire = $action->getLivewire();

        if ($livewire instanceof ListRecords || ! method_exists($livewire, 'getResource')) {
            return;
        }

        $page = $livewire instanceof ViewRecord ? 'view' : 'edit';

        $livewire->redirect(BookingResource::getUrl($page, ['record' => $record], panel: 'manage'));
    }
}
