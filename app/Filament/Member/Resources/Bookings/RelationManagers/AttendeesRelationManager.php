<?php

namespace App\Filament\Member\Resources\Bookings\RelationManagers;

use App\Models\Booking;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class AttendeesRelationManager extends RelationManager
{
    protected static ?string $title = 'Guest list';

    protected static string $relationship = 'attendees';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $actor = auth()->user();

        if (! $ownerRecord instanceof Booking || ! $actor instanceof User) {
            return false;
        }

        $ownerRecord->loadMissing('team');

        return $ownerRecord->team !== null
            && $actor->isCurrentMemberOf($ownerRecord->team);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
            ])
            ->content(fn (): View => view('filament.member.bookings.guest-list', [
                'booking' => $this->getOwnerRecord(),
            ]))
            ->paginated(false)
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
