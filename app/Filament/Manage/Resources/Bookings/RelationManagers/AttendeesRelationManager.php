<?php

namespace App\Filament\Manage\Resources\Bookings\RelationManagers;

use App\Actions\AddAttendeeAction;
use App\Actions\InviteAttendeesAction;
use App\Actions\OverrideAttendanceAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

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
                TextColumn::make('attendance.status')
                    ->badge()
                    ->icon(fn (BookingAttendeeStatus $state): Heroicon => $state->getIcon()),
            ])
            ->headerActions([
                Action::make('invite')
                    ->visible(fn (): bool => $this->booking()->acceptsInvites())
                    ->schema([
                        CheckboxList::make('user_ids')
                            ->label(__('Members'))
                            ->options(fn (): array => $this->membersNotOnBooking())
                            ->searchable()
                            ->columns(1)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        app(InviteAttendeesAction::class)(
                            $this->booking(),
                            $data['user_ids'] ?? [],
                            auth()->user(),
                        );
                    }),
                Action::make('add')
                    ->label(__('Add'))
                    ->visible(fn (): bool => ! $this->booking()->isCancelled()
                        && (bool) auth()->user()?->can('create', [BookingAttendance::class, $this->booking()]))
                    ->schema([
                        $this->memberSelect(existing: false),
                        ...$this->attendanceFields(),
                    ])
                    ->action(function (array $data): void {
                        $manager = auth()->user();
                        $response = $this->responseFrom($data);
                        $attendee = User::query()->find($data['user_id'] ?? null);

                        if (! $manager instanceof User || ! $attendee instanceof User || $response === null) {
                            return;
                        }

                        app(AddAttendeeAction::class)(
                            $this->booking(),
                            $attendee,
                            $manager,
                            $response->toStatus(),
                            $data['comment'] ?? null,
                        );
                    }),
            ])
            ->recordActions([
                Action::make('editAttendance')
                    ->label(__('Edit'))
                    ->authorize(function (User $record): bool {
                        $attendance = $record->attendance;

                        return $attendance instanceof BookingAttendance
                            && (bool) auth()->user()?->can('override', $attendance);
                    })
                    ->fillForm(fn (User $record): array => [
                        'user_id' => $record->getKey(),
                        'response' => BookingAttendeeResponse::tryFromStatus($record->attendance?->status)?->value,
                        'comment' => $record->attendance?->comment,
                    ])
                    ->schema([
                        $this->memberSelect(existing: true),
                        ...$this->attendanceFields(),
                    ])
                    ->action(function (User $record, array $data): void {
                        $manager = auth()->user();
                        $response = $this->responseFrom($data);

                        if (! $manager instanceof User || $response === null) {
                            return;
                        }

                        app(OverrideAttendanceAction::class)(
                            $this->booking(),
                            $record,
                            $manager,
                            $response->toStatus(),
                            $data['comment'] ?? null,
                        );
                    }),
                DetachAction::make()
                    ->authorize(fn (User $record): bool => $this->canRemoveAttendee($record))
                    ->after(function (User $record): void {
                        activity()
                            ->causedBy(auth()->user())
                            ->performedOn($this->booking())
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

    /**
     * @return array<string, string>
     */
    protected function membersNotOnBooking(): array
    {
        $booking = $this->booking();

        return User::query()
            ->whereNot('role', 'suspended')
            ->whereNotNull('email_verified_at')
            ->whereDoesntHave('bookings', fn (Builder $query) => $query->where('booking_id', $booking->id))
            ->ordered()
            ->pluck('name', 'id')
            ->all();
    }

    protected function memberSelect(bool $existing): Select
    {
        $select = Select::make('user_id')->label(__('Member'));

        if ($existing) {
            return $select
                ->options(fn (User $record): array => [$record->getKey() => $record->name])
                ->disabled()
                ->dehydrated(false);
        }

        return $select
            ->options(fn (): array => $this->membersNotOnBooking())
            ->searchable()
            ->required();
    }

    /**
     * @return array<int, ToggleButtons|Textarea>
     */
    protected function attendanceFields(): array
    {
        return [
            ToggleButtons::make('response')
                ->label(__('Status'))
                ->options(BookingAttendeeResponse::class)
                ->inline()
                ->required(),
            Textarea::make('comment'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function responseFrom(array $data): ?BookingAttendeeResponse
    {
        $response = $data['response'] ?? null;

        return $response instanceof BookingAttendeeResponse
            ? $response
            : BookingAttendeeResponse::tryFrom((string) $response);
    }

    protected function booking(): Booking
    {
        $booking = $this->getOwnerRecord();

        if (! $booking instanceof Booking) {
            throw new RuntimeException('Attendees belong to a booking.');
        }

        return $booking;
    }

    protected function canRemoveAttendee(User $attendee): bool
    {
        $attendance = $attendee->attendance;

        return $attendance instanceof BookingAttendance
            && (bool) auth()->user()?->can('delete', $attendance);
    }
}
