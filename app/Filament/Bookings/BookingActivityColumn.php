<?php

namespace App\Filament\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Support\Palette;
use Awcodes\BadgeableColumn\Components\Badge;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

final class BookingActivityColumn
{
    public static function make(bool $withTeamBadge = false): BadgeableColumn
    {
        $badges = [
            Badge::make('status')
                ->label(fn (Booking $record): string => $record->status->getLabel())
                ->color(fn (Booking $record): array => $record->status->getColor()),
            Badge::make('attendance')
                ->label(fn (Booking $record): ?string => self::attendance($record)?->getLabel())
                ->color(fn (Booking $record): array => self::attendance($record)?->getColor() ?? Palette::neutral())
                ->visible(fn (Booking $record): bool => self::attendance($record) !== null),
        ];

        if ($withTeamBadge) {
            $badges[] = Badge::make('team')
                ->label(fn (Booking $record): ?string => $record->team?->name)
                ->color(fn (Booking $record): array => $record->team?->brandColor()->getColor() ?? Palette::neutral())
                ->visible(fn (Booking $record): bool => $record->team !== null);
        }

        return BadgeableColumn::make('activity.name')
            ->label(__('Activity'))
            ->state(fn (Booking $record): string => $record->activityTitle())
            ->description(fn (Booking $record): ?string => $record->venue?->name)
            ->wrap()
            ->icon(fn (Booking $record): ?Heroicon => filled($record->activity_label)
                && $record->activity !== null
                ? Heroicon::OutlinedInformationCircle
                : null)
            ->iconPosition('after')
            ->tooltip(fn (Booking $record): ?string => filled($record->activity_label)
                && $record->activity !== null
                ? __('Original activity: :name', ['name' => $record->activity?->name])
                : null)
            ->suffixBadges($badges)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('activity_label', 'like', "%{$search}%")
                        ->orWhereHas('activity', fn (Builder $activityQuery) => $activityQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('venue', fn (Builder $venueQuery) => $venueQuery
                            ->where('name', 'like', "%{$search}%"));
                }));
    }

    private static function attendance(Booking $record): ?BookingAttendeeStatus
    {
        return $record->attendees
            ->firstWhere('id', auth()->id())
            ?->attendance
            ?->status;
    }
}
