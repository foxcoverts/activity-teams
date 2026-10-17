<?php

namespace App\Filament\Manage\Resources\Bookings\RelationManagers;

use App\Actions\InviteAttendeesAction;
use App\Models\User;
use Filament\Actions\Action;
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
            ]);
    }
}
