<?php

namespace App\Filament\Member\Resources\Bookings\Pages;

use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Bookings\BookingRespondAction;
use App\Filament\Bookings\RefreshesWhenAttendanceChanges;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Filament\Member\Resources\Bookings\BookingTimeline;
use App\Livewire\BookingComments;
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

            if ($record->team !== null && $user->isCurrentMemberOf($record->team)) {
                $sidebar[] = $this->getRelationManagersContentComponent();
            }

            $visibleRelated = $record->related()
                ->get()
                ->filter(fn (Booking $related): bool => $user->can('view', $related));

            if ($visibleRelated->isNotEmpty()) {
                $sidebar[] = Livewire::make(RelatedBookings::class, [
                    'record' => $record,
                    'panel' => 'member',
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
                        ]),
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
        $actions = [];
        $record = $this->getRecord();
        $user = auth()->user();

        if ($record instanceof Booking && $user instanceof User && $record->isLead($user)) {
            $actions[] = BookingKeysAction::make();
        }

        $actions[] = BookingRespondAction::make();

        return $actions;
    }
}
