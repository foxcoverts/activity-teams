<?php

namespace App\Filament\Teams\Resources\Bookings\Pages;

use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Bookings\BookingViewRespondActions;
use App\Filament\Bookings\ConfirmAttendeesAction;
use App\Filament\Bookings\RefreshesWhenAttendanceChanges;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Filament\Teams\Resources\Bookings\BookingTimeline;
use App\Livewire\BookingComments;
use App\Livewire\BookingTasks;
use App\Livewire\RelatedBookings;
use App\Models\Booking;
use App\Models\User;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

class ViewBooking extends ViewRecord
{
    use RefreshesWhenAttendanceChanges;

    protected static string $resource = BookingResource::class;

    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $user = auth()->user();
        $sidebar = [];

        if ($record instanceof Booking && $user instanceof User) {
            $record->loadMissing('team');

            if (
                $record->team !== null
                && ($user->isCurrentMemberOf($record->team) || $user->can('update', $record))
            ) {
                $sidebar[] = $this->getRelationManagersContentComponent();
            }

            if (RelatedBookings::shouldShowPanel($record, $user)) {
                $sidebar[] = Livewire::make(RelatedBookings::class, [
                    'record' => $record,
                ]);
            }

            if (BookingTasks::shouldShowPanel($record, $user)) {
                $sidebar[] = Livewire::make(BookingTasks::class, [
                    'record' => $record,
                ]);
            }
        }

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 5])
                ->schema([
                    Group::make([
                        $this->getInfolistContentComponent(),
                        Livewire::make(BookingComments::class, [
                            'record' => $record,
                            'highlightId' => is_string(request()->query('comment'))
                                ? request()->query('comment')
                                : null,
                        ])->visible(function (): bool {
                            $booking = $this->getRecord();
                            $viewer = auth()->user();

                            return $booking instanceof Booking
                                && $viewer instanceof User
                                && BookingComments::shouldShowPanel($booking, $viewer);
                        }),
                    ])
                        ->columnSpan(['default' => 1, 'lg' => 3]),
                    Group::make($sidebar)
                        ->extraAttributes(['class' => 'invite-panel'])
                        ->columnSpan(['default' => 1, 'lg' => 2]),
                    EmbeddedSchema::make('timeline')
                        ->columnSpan(['default' => 1, 'lg' => 3]),
                ]),
        ]);
    }

    public function timeline(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
                BookingTimeline::section(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (! $record instanceof Booking || ! $user instanceof User) {
            return [];
        }

        $record->loadMissing('team');

        $actions = [];

        if ($record->isLead($user) || ($record->team !== null && $user->manages($record->team))) {
            $actions[] = BookingKeysAction::make();
            $actions[] = ConfirmAttendeesAction::make();
        }

        $actions[] = BookingViewRespondActions::make();

        $managesTeam = $record->team !== null && $user->manages($record->team);

        if ($managesTeam) {
            $actions[] = BookingStatusActions::group()->record($record);
        }

        return $actions;
    }
}
