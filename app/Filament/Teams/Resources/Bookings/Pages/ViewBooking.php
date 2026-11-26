<?php

namespace App\Filament\Teams\Resources\Bookings\Pages;

use App\Enums\TimelineAudience;
use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Bookings\BookingViewRespondActions;
use App\Filament\Bookings\CompleteDebriefAction;
use App\Filament\Bookings\LogbookEntryAction;
use App\Filament\Bookings\RefreshesWhenAttendanceChanges;
use App\Filament\Concerns\TogglesTimelineAudience;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Filament\Teams\Resources\Bookings\BookingTimeline;
use App\Livewire\BookingTasks;
use App\Livewire\Comments;
use App\Livewire\RelatedBookings;
use App\Models\Booking;
use App\Models\User;
use App\Support\MarkSubjectInboxRead;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewBooking extends ViewRecord
{
    use RefreshesWhenAttendanceChanges;
    use TogglesTimelineAudience;

    protected static string $resource = BookingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = auth()->user();
        $booking = $this->getRecord();

        if ($user instanceof User && $booking instanceof Booking) {
            app(MarkSubjectInboxRead::class)($user, $booking);
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

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
                        Livewire::make(Comments::class, [
                            'record' => $record,
                            'highlightId' => is_string(request()->query('comment'))
                                ? request()->query('comment')
                                : null,
                        ])->visible(function (): bool {
                            $booking = $this->getRecord();
                            $viewer = auth()->user();

                            return $booking instanceof Booking
                                && $viewer instanceof User
                                && Comments::shouldShowPanel($booking, $viewer);
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
        $record = $this->getRecord();
        $viewer = auth()->user();
        $manages = $record instanceof Booking
            && $viewer instanceof User
            && $record->team !== null
            && $viewer->manages($record->team);

        $audience = $manages && ! $this->timelineAsMember
            ? TimelineAudience::Manager
            : TimelineAudience::Member;

        return $schema
            ->record($record)
            ->components([
                BookingTimeline::section(
                    audience: $audience,
                    showAudienceToggle: $manages,
                    livewire: $this,
                    booking: $record instanceof Booking ? $record : null,
                    viewer: $viewer instanceof User ? $viewer : null,
                ),
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
            $actions[] = CompleteDebriefAction::make();
        }

        $actions[] = LogbookEntryAction::make();
        $actions[] = BookingViewRespondActions::make();

        $managesTeam = $record->team !== null && $user->manages($record->team);

        if ($managesTeam) {
            $actions[] = BookingStatusActions::group()->record($record);
        }

        return $actions;
    }
}
