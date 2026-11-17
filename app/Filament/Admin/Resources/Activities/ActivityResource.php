<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Admin\Resources\Activities\RelationManagers\ActivityAudiencesRelationManager;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Catalog;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Activity'))
                    ->icon(Heroicon::OutlinedFlag)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                        Toggle::make('requires_activity_label')
                            ->label(__('Require activity name on bookings'))
                            ->helperText(__('Use when the catalog name is only for keys and staffing. Each booking must then give a proper activity name.'))
                            ->default(false),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Activity'))
                    ->icon(Heroicon::OutlinedFlag)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->description(fn (Activity $record): ?string => filled($record->description) ? $record->description : null)
                    ->searchable(['name', 'description']),
                TextColumn::make('audiences.name')
                    ->label(__('Audiences'))
                    ->placeholder(__('No audiences')),
            ])
            ->filters([
                Filter::make('without_team')
                    ->label(__('Not on a team'))
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('teams')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ActivityAudiencesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'view' => ViewActivity::route('/{record}'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
