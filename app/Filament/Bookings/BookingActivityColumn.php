<?php

namespace App\Filament\Bookings;

use App\Enums\BookingAttendeeStatus;
use App\Enums\PastBookingAttendeeStatus;
use App\Models\Booking;
use App\Support\Palette;
use Awcodes\BadgeableColumn\Components\Badge;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

final class BookingActivityColumn
{
    public static function make(bool $withTeamBadge = false): BadgeableColumn
    {
        $badges = [];

        if ($withTeamBadge) {
            $badges[] = Badge::make('team')
                ->label(fn (Booking $record): ?string => $record->team?->name)
                ->color(fn (Booking $record): array => $record->team?->brandColor()->getColor() ?? Palette::neutral())
                ->visible(function (Booking $record): bool {
                    if ($record->team === null) {
                        return false;
                    }

                    $livewire = Livewire::current();

                    // Hide when the list is already filtered to one team.
                    // BadgeableColumn badges do not inject HasTable like TextColumn.
                    if ($livewire instanceof HasTable) {
                        return blank($livewire->getTableFilterState('team_id')['value'] ?? null);
                    }

                    return true;
                });
        }

        $column = BadgeableColumn::make('display_name')
            ->label(__('Activity'))
            ->state(fn (Booking $record): string => $record->display_name)
            ->description(fn (Booking $record): ?string => $record->venue?->name)
            ->wrap()
            ->icon(fn (Booking $record): Heroicon => $record->status->getIcon())
            ->iconColor(fn (Booking $record): array => $record->status->getColor())
            ->iconPosition(IconPosition::Before)
            ->tooltip(function (Booking $record): string {
                $parts = [$record->status->getLabel()];

                if (filled($record->activity_label) && $record->activity !== null) {
                    $parts[] = __('Original activity: :name', ['name' => $record->activity->name]);
                }

                return implode(' · ', $parts);
            })
            ->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('activity_label', 'like', "%{$search}%")
                        ->orWhere('group_name', 'like', "%{$search}%")
                        ->orWhereHas('activity', fn (Builder $activityQuery) => $activityQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('audience', fn (Builder $audienceQuery) => $audienceQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('venue', fn (Builder $venueQuery) => $venueQuery
                            ->where('name', 'like', "%{$search}%"));
                }));

        if ($badges !== []) {
            $column->suffixBadges($badges);
        }

        return $column;
    }

    public static function attendanceStatusColumn(): TextColumn
    {
        return TextColumn::make('attendance_status')
            ->label(__('Your response'))
            ->state(fn (Booking $record): BookingAttendeeStatus|PastBookingAttendeeStatus|null => self::attendance($record))
            ->badge()
            ->placeholder('-')
            ->visibleFrom('md');
    }

    /**
     * Left attendance bar on the table row (same colours as the calendar list).
     *
     * @return list<string>
     */
    public static function attendanceRecordClasses(Booking $record): array
    {
        $status = self::attendance($record);

        if ($status === null) {
            return [];
        }

        return match (BookingAttendeeStatus::from($status->value)) {
            BookingAttendeeStatus::Accepted => ['booking-row--attendance-accepted'],
            BookingAttendeeStatus::Tentative => ['booking-row--attendance-tentative'],
            BookingAttendeeStatus::NeedsAction => ['booking-row--attendance-needs-action'],
            BookingAttendeeStatus::Declined => ['booking-row--attendance-declined'],
        };
    }

    private static function attendance(Booking $record): BookingAttendeeStatus|PastBookingAttendeeStatus|null
    {
        return $record->attendees
            ->firstWhere('id', auth()->id())
            ?->attendance
            ?->status;
    }
}
