<?php

namespace App\Filament\Teams\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\TeamFeature;
use App\Filament\Bookings\LocationMapAction;
use App\Filament\Teams\Resources\Members\MemberResource;
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
                        ->visible(fn (Booking $record): bool => filled($record->audience?->name)),
                    TextEntry::make('group_name')
                        ->label(__('Group'))
                        ->visible(fn (Booking $record): bool => filled($record->group_name))
                        ->columnSpanFull(),
                    TextEntry::make('notes')
                        ->label(__('Notes'))
                        ->markdown()
                        ->visible(fn (Booking $record): bool => filled($record->notes))
                        ->columnSpanFull(),
                    TextEntry::make('lead_instructor_notes')
                        ->label(__('Lead instructor notes'))
                        ->markdown()
                        ->visible(function (Booking $record): bool {
                            $user = auth()->user();
                            $record->loadMissing('team');

                            return $record->requires_lead_instructor
                                && filled($record->lead_instructor_notes)
                                && $user instanceof User
                                && $record->team !== null
                                && ($record->isLead($user) || $user->manages($record->team));
                        })
                        ->columnSpanFull(),
                ]),
            Section::make(__('Debrief'))
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->columns(1)
                ->columnSpanFull()
                ->visible(function (Booking $record): bool {
                    $record->loadMissing(['team', 'debrief.equipmentSets', 'debrief.completedBy']);

                    return $record->team !== null
                        && $record->team->featureEnabled(TeamFeature::Debrief)
                        && $record->debrief !== null;
                })
                ->schema([
                    TextEntry::make('debrief.hours')
                        ->label(__('Operating hours'))
                        ->placeholder('-'),
                    TextEntry::make('debrief.participant_count')
                        ->label(__('Participant numbers'))
                        ->placeholder('-'),
                    TextEntry::make('debrief.weather')
                        ->label(__('Weather'))
                        ->placeholder('-'),
                    TextEntry::make('debrief.notes')
                        ->label(__('Notes'))
                        ->visible(fn (Booking $record): bool => filled($record->debrief?->notes)),
                    TextEntry::make('debrief.equipmentSets.name')
                        ->label(__('Equipment used'))
                        ->badge()
                        ->placeholder('-')
                        ->visible(fn (Booking $record): bool => $record->debrief?->equipmentSets->isNotEmpty() ?? false),
                    TextEntry::make('debrief.completed_at')
                        ->label(__('Completed'))
                        ->dateTime('j M Y, H:i')
                        ->placeholder('-'),
                    TextEntry::make('debrief.completedBy.name')
                        ->label(__('Completed by'))
                        ->placeholder('-')
                        ->url(fn (Booking $record): ?string => MemberResource::viewUrlFor(
                            $record->debrief?->completedBy,
                            $record->team,
                        ))
                        ->color('primary'),
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
