<?php

namespace App\Filament\Teams\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Filament\Bookings\LocationMapAction;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingWhen;
use App\Support\Palette;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class BookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components());
    }

    public static function components(): array
    {
        return [
            Section::make(fn (?Booking $record): string => __(':Status booking', [
                'status' => $record?->status->getLabel() ?? __('Booking'),
            ]))
                ->icon(fn (?Booking $record): Heroicon => ($record?->status ?? BookingStatus::Tentative)->getIcon())
                ->iconColor(fn (?Booking $record): array => $record?->isPast()
                    ? Palette::neutral()
                    : ($record?->status ?? BookingStatus::Tentative)->getColor())
                ->afterHeader([
                    static::endedBadge(),
                ])
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('when')
                        ->label(__('When'))
                        ->state(fn (Booking $record): string => BookingWhen::format($record)),
                    TextEntry::make('location')
                        ->label(__('Location'))
                        ->state(fn (Booking $record): array => array_filter([
                            $record->location->name,
                            ...$record->location->addressArray(),
                        ]))
                        ->listWithLineBreaks()->limitList(1)->expandableLimitedList()
                        ->afterLabel([
                            LocationMapAction::make(),
                        ]),
                    TextEntry::make('activity_title')
                        ->label(__('Activity'))
                        ->state(fn (Booking $record): string => $record->activityTitle())
                        ->icon(fn (Booking $record): ?Heroicon => filled($record->activity_label)
                            && $record->activity !== null
                            ? Heroicon::OutlinedInformationCircle
                            : null)
                        ->iconPosition('after')
                        ->tooltip(fn (Booking $record): ?string => filled($record->activity_label)
                            && $record->activity !== null
                            ? __('Original activity: :name', ['name' => $record->activity?->name])
                            : null),
                    TextEntry::make('audience.name')
                        ->label(__('Audience'))
                        ->placeholder('-'),
                    TextEntry::make('group_name')
                        ->label(__('Group'))
                        ->placeholder('-')
                        ->columnSpanFull(),
                    TextEntry::make('notes')
                        ->label(__('Notes'))
                        ->visible(fn (Booking $record): bool => filled($record->notes))
                        ->columnSpanFull(),
                    TextEntry::make('lead_instructor_notes')
                        ->label(__('Lead instructor notes'))
                        ->visible(function (Booking $record): bool {
                            $user = auth()->user();
                            $record->loadMissing('team');

                            return filled($record->lead_instructor_notes)
                                && $user instanceof User
                                && $record->team !== null
                                && ($record->isLead($user) || $user->manages($record->team));
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected static function endedBadge(): Text
    {
        return Text::make(__('Ended'))
            ->badge()
            ->size(Size::Small)
            ->color(Palette::neutral())
            ->visible(fn (?Booking $record): bool => $record?->isPast() ?? false);
    }
}
