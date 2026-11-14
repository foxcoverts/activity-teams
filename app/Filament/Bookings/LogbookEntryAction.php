<?php

namespace App\Filament\Bookings;

use App\Filament\Support\SubmitsOnModEnter;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\LogbookEntry;
use App\Models\User;
use App\Support\BookingWhen;
use App\Support\Logbook;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

class LogbookEntryAction
{
    public static function make(): Action
    {
        return Action::make('logbook')
            ->label(fn (Booking $record): string => self::entryFor(auth()->user(), $record) instanceof LogbookEntry
                ? __('Edit logbook')
                : __('Add to logbook'))
            ->icon(fn (Booking $record): Heroicon => self::entryFor(auth()->user(), $record) instanceof LogbookEntry
                ? Heroicon::OutlinedPencilSquare
                : Heroicon::OutlinedBookOpen)
            ->color(function (Booking $record): array {
                $entry = self::entryFor(auth()->user(), $record);

                if ($entry instanceof LogbookEntry) {
                    return $record->isPast() ? Palette::neutral() : Palette::positive();
                }

                return Palette::positive();
            })
            ->modalHeading(fn (Booking $record): string => self::entryFor(auth()->user(), $record) instanceof LogbookEntry
                ? __('Edit logbook entry')
                : __('Add to logbook'))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Save'))
            ->visible(fn (Booking $record): bool => self::isAvailable($record, auth()->user()))
            ->fillForm(function (Booking $record): array {
                $user = auth()->user();
                assert($user instanceof User);

                $entry = self::entryFor($user, $record);

                return $entry instanceof LogbookEntry
                    ? self::editFormState($entry)
                    : app(Logbook::class)->addFormState($record);
            })
            ->schema(fn (Booking $record): array => self::schema($record))
            ->extraModalFooterActions(fn (Action $action): array => [
                self::copyFromDebriefAction($action),
            ])
            ->action(function (Booking $record, array $data): void {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return;
                }

                $entry = self::entryFor($user, $record);

                try {
                    if ($entry instanceof LogbookEntry) {
                        app(Logbook::class)->update($user, $entry, $data);
                    } else {
                        app(Logbook::class)->add($user, $record, $data, prefillFromDebrief: false);
                    }
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to save.'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title($entry instanceof LogbookEntry ? __('Logbook entry saved') : __('Added to logbook'))
                    ->send();
            });
    }

    public static function isAvailable(Booking $booking, mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return self::entryFor($user, $booking) instanceof LogbookEntry
            || app(Logbook::class)->canAdd($user, $booking);
    }

    public static function entryFor(mixed $user, Booking $booking): ?LogbookEntry
    {
        if (! $user instanceof User) {
            return null;
        }

        return LogbookEntry::query()
            ->where('user_id', $user->getKey())
            ->where('booking_id', $booking->getKey())
            ->first();
    }

    /**
     * @return array{hours: mixed, participant_count: mixed, weather: mixed, notes: mixed, debrief_notes: ?string}
     */
    public static function editFormState(LogbookEntry $entry): array
    {
        $entry->loadMissing('booking.debrief');

        return [
            'hours' => $entry->hours,
            'participant_count' => $entry->participant_count,
            'weather' => $entry->weather,
            'notes' => $entry->notes,
            'debrief_notes' => $entry->booking?->debrief?->notes,
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function schema(Booking $booking): array
    {
        $booking->loadMissing(['activity', 'audience', 'venue', 'team', 'debrief']);

        return [
            self::bookingSummarySection($booking),
            Section::make(__('Session'))
                ->icon(Heroicon::OutlinedClock)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('hours')
                        ->label(__('Hours'))
                        ->numeric()
                        ->step(0.25)
                        ->minValue(0)
                        ->maxValue(999)
                        ->inputMode('decimal'),
                    TextInput::make('participant_count')
                        ->label(__('Participants'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(9999),
                    TextInput::make('weather')
                        ->label(__('Weather'))
                        ->maxLength(255),
                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->rows(4)
                        ->extraAlpineAttributes(SubmitsOnModEnter::attributes()),
                    Placeholder::make('debrief_notes')
                        ->label(__('Debrief notes'))
                        ->content(fn (): string => filled($booking->debrief?->notes)
                            ? (string) $booking->debrief->notes
                            : '—')
                        ->visible(fn (): bool => filled($booking->debrief?->notes)),
                ]),
        ];
    }

    public static function bookingSummarySection(Booking $booking): Section
    {
        $team = $booking->team;
        $bookingUrl = $team !== null
            ? BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $team)
            : null;

        return Section::make(__('Booking'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->columns(1)
            ->columnSpanFull()
            ->headerActions([
                Action::make('viewBooking')
                    ->label(__('View booking'))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->link()
                    ->url($bookingUrl)
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled($bookingUrl)),
            ])
            ->schema([
                Placeholder::make('what')
                    ->label(__('What'))
                    ->content($booking->activityTitle() ?: '—'),
                Placeholder::make('when')
                    ->label(__('When'))
                    ->content(self::whenLabel($booking)),
                Placeholder::make('where')
                    ->label(__('Where'))
                    ->content($booking->venue?->name ?: '—'),
            ]);
    }

    public static function copyFromDebriefAction(Action $parent): Action
    {
        return Action::make('copyFromDebrief')
            ->label(__('Copy from debrief'))
            ->color(Palette::neutral())
            ->visible(fn (): bool => self::debriefFor($parent->getRecord()) !== null)
            ->action(function () use ($parent): void {
                $debrief = self::debriefFor($parent->getRecord());

                if ($debrief === null) {
                    Notification::make()
                        ->danger()
                        ->title(__('This booking has no debrief to copy from.'))
                        ->send();

                    return;
                }

                $livewire = $parent->getLivewire();
                $parentIndex = $parent->getNestingIndex();

                if (! $livewire instanceof HasActions || $parentIndex === null) {
                    return;
                }

                $schema = $livewire->getSchema("mountedActionSchema{$parentIndex}");

                if ($schema === null) {
                    return;
                }

                $schema->fillPartially([
                    'hours' => $debrief->hours,
                    'participant_count' => $debrief->participant_count,
                    'weather' => $debrief->weather,
                    'debrief_notes' => $debrief->notes,
                ], [
                    'hours',
                    'participant_count',
                    'weather',
                    'debrief_notes',
                ]);

                Notification::make()
                    ->success()
                    ->title(__('Copied hours, participants, and weather from the debrief.'))
                    ->send();
            });
    }

    public static function debriefFor(mixed $record): ?BookingDebrief
    {
        if ($record instanceof LogbookEntry) {
            $record->loadMissing('booking.debrief');

            return $record->booking?->debrief;
        }

        if ($record instanceof Booking) {
            $record->loadMissing('debrief');

            return $record->debrief;
        }

        return null;
    }

    private static function whenLabel(Booking $booking): string
    {
        if ($booking->start_at === null || $booking->end_at === null) {
            return '—';
        }

        return BookingWhen::format($booking);
    }
}
