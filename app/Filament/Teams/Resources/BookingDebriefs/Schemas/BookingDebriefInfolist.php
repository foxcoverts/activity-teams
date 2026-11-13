<?php

namespace App\Filament\Teams\Resources\BookingDebriefs\Schemas;

use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Support\BookingWhen;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BookingDebriefInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Booking'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('booking.activity_title')
                        ->label(__('Activity'))
                        ->state(fn (BookingDebrief $record): string => $record->booking?->activityTitle() ?? '-')
                        ->url(fn (BookingDebrief $record): ?string => static::bookingUrl($record->booking))
                        ->color('primary'),
                    TextEntry::make('booking.audience.name')
                        ->label(__('Audience'))
                        ->placeholder('-')
                        ->visible(fn (BookingDebrief $record): bool => filled($record->booking?->audience?->name)),
                    TextEntry::make('booking.when')
                        ->label(__('When'))
                        ->state(fn (BookingDebrief $record): string => $record->booking instanceof Booking
                            ? BookingWhen::format($record->booking)
                            : '-'),
                    TextEntry::make('booking.venue.name')
                        ->label(__('Venue'))
                        ->placeholder('-'),
                ]),
            Section::make(__('Debrief'))
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('hours')
                        ->label(__('Operating hours'))
                        ->placeholder('-'),
                    TextEntry::make('participant_count')
                        ->label(__('Participant numbers'))
                        ->placeholder('-'),
                    TextEntry::make('weather')
                        ->label(__('Weather'))
                        ->placeholder('-'),
                    TextEntry::make('notes')
                        ->label(__('Notes'))
                        ->visible(fn (BookingDebrief $record): bool => filled($record->notes)),
                    TextEntry::make('equipmentSets.name')
                        ->label(__('Equipment used'))
                        ->badge()
                        ->placeholder('-')
                        ->visible(fn (BookingDebrief $record): bool => $record->equipmentSets->isNotEmpty()),
                    TextEntry::make('completed_at')
                        ->label(__('Completed'))
                        ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                        ->dateTime('j M Y, H:i')
                        ->placeholder('-'),
                    TextEntry::make('completedBy.name')
                        ->label(__('Completed by'))
                        ->placeholder('-')
                        ->url(fn (BookingDebrief $record): ?string => MemberResource::viewUrlFor(
                            $record->completedBy,
                            $record->booking?->team,
                        ))
                        ->color('primary'),
                ]),
        ]);
    }

    private static function bookingUrl(?Booking $booking): ?string
    {
        if ($booking === null || $booking->team === null) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team);
    }
}
