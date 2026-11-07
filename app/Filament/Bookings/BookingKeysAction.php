<?php

namespace App\Filament\Bookings;

use App\Enums\KeyHolderAttendance;
use App\Enums\KeySituation;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Booking;
use App\Models\Team;
use App\Support\BookingPeople;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

class BookingKeysAction
{
    public static function make(): Action
    {
        return Action::make('keys')
            ->label(__('Keys'))
            ->modalHeading(__('Keys'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->visible(fn (Booking $record): bool => $record->requiredLocks()->isNotEmpty())
            ->icon(fn (Booking $record): Heroicon => self::attendance($record)->getIcon())
            ->color(fn (Booking $record): array => self::attendance($record)->getColor())
            ->tooltip(fn (Booking $record): string => self::attendance($record)->getLabel())
            ->modalContent(fn (Booking $record): View => view('filament.bookings.keys', [
                'keys' => self::lines($record),
            ]));
    }

    /**
     * @return list<array{name: string, groups: list<array{situation: KeySituation, rings: list<array{name: string, holder: ?string, holder_id: ?string, url: ?string}>}>}>
     */
    public static function lines(Booking $booking): array
    {
        $memberIds = self::memberIds($booking);
        $actor = auth()->user();
        $linkMembers = $booking->team instanceof Team
            && $actor !== null
            && $actor->manages($booking->team);

        return collect(BookingPeople::keyAccess($booking))
            ->map(function (array $key) use ($booking, $linkMembers, $memberIds): array {
                $key['groups'] = collect($key['groups'])
                    ->map(function (array $group) use ($booking, $linkMembers, $memberIds): array {
                        $group['rings'] = collect($group['rings'])
                            ->map(function (array $ring) use ($booking, $linkMembers, $memberIds): array {
                                $ring['url'] = $linkMembers
                                    && filled($ring['holder_id'])
                                    && isset($memberIds[$ring['holder_id']])
                                    ? MemberResource::getUrl('view', ['record' => $ring['holder_id']], panel: 'teams', tenant: $booking->team)
                                    : null;

                                return $ring;
                            })
                            ->all();

                        return $group;
                    })
                    ->all();

                return $key;
            })
            ->all();
    }

    private static function attendance(Booking $booking): KeyHolderAttendance
    {
        return BookingPeople::holderAttendance($booking);
    }

    /**
     * @return array<string, true>
     */
    private static function memberIds(Booking $booking): array
    {
        $booking->loadMissing('team');

        if (! $booking->team instanceof Team) {
            return [];
        }

        return $booking->team->members()
            ->pluck('users.id')
            ->mapWithKeys(fn (int|string $id): array => [(string) $id => true])
            ->all();
    }
}
