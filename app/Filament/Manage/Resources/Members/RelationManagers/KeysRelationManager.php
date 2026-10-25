<?php

namespace App\Filament\Manage\Resources\Members\RelationManagers;

use App\Filament\Manage\Resources\Members\MemberResource;
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
    protected static string $relationship = 'openedKeys';

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
            && $ownerRecord->openedKeysQuery($team)->exists();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRelationship(): Relation|Builder
    {
        return $this->openedKeysQuery();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->openedKeysQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Key'))
                    ->searchable(),
                TextColumn::make('required_for')
                    ->label(__('Required for'))
                    ->state(function (Key $record): array {
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
     * @return Builder<Key>
     */
    private function openedKeysQuery(): Builder
    {
        $owner = $this->getOwnerRecord();
        abort_unless($owner instanceof User, 404);

        return $owner->openedKeysQuery(MemberResource::currentTeam())
            ->with(['requirements.activity', 'requirements.venue']);
    }
}
