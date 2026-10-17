<?php

namespace App\Filament\Manage\Resources\Bookings\RelationManagers;

use App\Actions\InviteAttendeesAction;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendeesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendees';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email'),
                TextColumn::make('attendance.status')->badge(),
            ])
            ->headerActions([
                Action::make('invite')
                    ->visible(fn (): bool => $this->getOwnerRecord()->acceptsInvites())
                    ->schema([
                        CheckboxList::make('user_ids')
                            ->label(__('Members'))
                            ->options(function (): array {
                                $booking = $this->getOwnerRecord();

                                return User::query()
                                    ->whereNot('role', 'suspended')
                                    ->whereNotNull('email_verified_at')
                                    ->whereDoesntHave('bookings', fn (Builder $query) => $query->where('booking_id', $booking->id))
                                    ->ordered()
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->columns(1)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        app(InviteAttendeesAction::class)(
                            $this->getOwnerRecord(),
                            $data['user_ids'] ?? [],
                            auth()->user(),
                        );
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->authorize(fn (User $record): bool => $this->canRemoveAttendee($record))
                    ->after(function (User $record): void {
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($this->getOwnerRecord())
                            ->withProperties(['user_id' => $record->getKey()])
                            ->event('attendee_removed')
                            ->log('Attendee removed');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->authorizeIndividualRecords(fn (User $record): bool => $this->canRemoveAttendee($record)),
                ]),
            ]);
    }

    protected function canRemoveAttendee(User $attendee): bool
    {
        $attendance = $attendee->attendance;

        return $attendance instanceof BookingAttendance
            && (bool) auth()->user()?->can('delete', $attendance);
    }
}
