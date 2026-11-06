<?php

namespace App\Filament\Teams\Resources\Members\RelationManagers;

use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Lock;
use App\Models\Team;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class LocksRelationManager extends RelationManager
{
    protected static string $relationship = 'openedLocks';

    protected static ?string $title = 'Locks';

    protected static ?string $modelLabel = 'lock';

    protected static bool $shouldSkipAuthorization = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! MemberResource::canViewAny() || ! $ownerRecord instanceof User) {
            return false;
        }

        $team = MemberResource::currentTeam();

        return $team instanceof Team
            && $ownerRecord->openedLocksQuery($team)->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        return $this->openedLocksQuery();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->openedLocksQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Lock'))
                    ->searchable(),
                TextColumn::make('required_for')
                    ->label(__('Required for'))
                    ->state(function (Lock $record): array {
                        $team = MemberResource::currentTeam();

                        return $team instanceof Team ? $record->requirementSummariesFor($team) : [];
                    })
                    ->listWithLineBreaks(),
            ])
            ->headerActions([])
            ->recordUrl(null)
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return Builder<Lock>
     */
    private function openedLocksQuery(): Builder
    {
        $owner = $this->getOwnerRecord();
        abort_unless($owner instanceof User, 404);

        return $owner->openedLocksQuery(MemberResource::currentTeam())
            ->with(['requirements.activity', 'requirements.venue']);
    }
}
