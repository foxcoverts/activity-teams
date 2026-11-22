<?php

namespace App\Filament\Teams\Resources\Members\RelationManagers;

use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class KeysRelationManager extends RelationManager
{
    protected static string $relationship = 'keys';

    protected static ?string $title = 'Keys';

    protected static ?string $modelLabel = 'key';

    protected static bool $shouldSkipAuthorization = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! MemberResource::canViewAny() || ! $ownerRecord instanceof User) {
            return false;
        }

        $team = MemberResource::currentTeam();

        return $team instanceof Team
            && $ownerRecord->keys()->relevantTo($team)->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        return $this->heldKeysQuery();
    }

    public function table(Table $table): Table
    {
        $team = MemberResource::currentTeam();

        return $table
            ->query(fn (): Builder => $this->heldKeysQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Key'))
                    ->searchable(),
                TextColumn::make('unlocks')
                    ->label(__('Unlocks'))
                    ->state(fn (Key $record): array => $record->lockNames())
                    ->listWithLineBreaks()
                    ->placeholder('-'),
                TextColumn::make('required_for')
                    ->label(__('Required for'))
                    ->state(fn (Key $record): array => $team instanceof Team
                        ? $record->requirementSummariesFor($team)
                        : [])
                    ->listWithLineBreaks()
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->recordUrl(fn (Key $record): ?string => $team instanceof Team
                ? KeyResource::getUrl('view', ['record' => $record], panel: 'teams', tenant: $team)
                : null)
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * @return Builder<Key>
     */
    private function heldKeysQuery(): Builder
    {
        $owner = $this->getOwnerRecord();
        abort_unless($owner instanceof User, 404);

        $team = MemberResource::currentTeam();
        abort_unless($team instanceof Team, 404);

        return $owner->keys()
            ->getQuery()
            ->relevantTo($team)
            ->with([
                'locks.covers.requirements.activity',
                'locks.covers.requirements.venue',
                'locks.requirements.activity',
                'locks.requirements.venue',
            ])
            ->orderBy('name');
    }
}
