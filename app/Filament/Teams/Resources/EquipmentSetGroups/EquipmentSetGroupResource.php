<?php

namespace App\Filament\Teams\Resources\EquipmentSetGroups;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\EquipmentSetGroups\Pages\ManageEquipmentSetGroups;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EquipmentSetGroupResource extends Resource
{
    protected static ?string $model = EquipmentSetGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Setup;

    protected static ?string $navigationParentItem = 'Equipment sets';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Groups';

    protected static ?string $modelLabel = 'group';

    protected static ?string $pluralModelLabel = 'groups';

    protected static ?string $slug = 'equipment-set-groups';

    protected static ?string $recordTitleAttribute = 'label';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Group'))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('label')
                        ->label(__('Label'))
                        ->required()
                        ->maxLength(255)
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: function ($rule) {
                                $team = static::currentTeam();

                                return $team === null
                                    ? $rule
                                    : $rule->where('team_id', $team->getKey());
                            },
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rank')
            ->reorderable('rank')
            ->columns([
                TextColumn::make('label')
                    ->label(__('Label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipment_sets_count')
                    ->counts('equipmentSets')
                    ->label(__('Sets')),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label(__('Move up'))
                    ->icon(Heroicon::OutlinedArrowUp)
                    ->iconButton()
                    ->visible(fn (EquipmentSetGroup $record): bool => $record->canMoveUp())
                    ->action(function (EquipmentSetGroup $record): void {
                        $record->moveUp();
                    }),
                Action::make('moveDown')
                    ->label(__('Move down'))
                    ->icon(Heroicon::OutlinedArrowDown)
                    ->iconButton()
                    ->visible(fn (EquipmentSetGroup $record): bool => $record->canMoveDown())
                    ->action(function (EquipmentSetGroup $record): void {
                        $record->moveDown();
                    }),
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (EquipmentSetGroup $record): void {
                        $record->deleteAndReassign();
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withCount('equipmentSets');
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        EquipmentSetGroup::ensureDefaultFor($team);

        return $query->whereBelongsTo($team);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEquipmentSetGroups::route('/'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
