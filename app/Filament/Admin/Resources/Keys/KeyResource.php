<?php

namespace App\Filament\Admin\Resources\Keys;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\EditKey;
use App\Filament\Admin\Resources\Keys\Pages\ListKeys;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Keys\RelationManagers\CoversRelationManager;
use App\Filament\Admin\Resources\Keys\RelationManagers\RingsRelationManager;
use App\Models\Key;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'requirements.activity',
            'requirements.venue',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Key'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                    ]),
                Section::make(__('Required for'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('requirements')
                            ->relationship()
                            ->hiddenLabel()
                            ->helperText(__('Each row is an activity at a session venue. The lock can be somewhere else.'))
                            ->schema([
                                Select::make('activity_id')
                                    ->label(__('Activity'))
                                    ->relationship('activity', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Select::make('venue_id')
                                    ->label(__('Session venue'))
                                    ->relationship('venue', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ])
                            ->columns(1)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Covers'))
                    ->icon(Heroicon::OutlinedLink)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('covers')
                            ->hiddenLabel()
                            ->helperText(__('Holding this key counts as holding each of these. A key cannot cover itself.'))
                            ->relationship(
                                name: 'covers',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                                ignoreRecord: true,
                            )
                            ->multiple()
                            ->preload()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Key'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                    ]),
                Section::make(__('Required for'))
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('requirements')
                            ->hiddenLabel()
                            ->placeholder(__('Not required for a session yet.'))
                            ->schema([
                                TextEntry::make('activity.name')
                                    ->label(__('Activity')),
                                TextEntry::make('venue.name')
                                    ->label(__('Session venue')),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('requirements_count')
                    ->counts('requirements')
                    ->label(__('Required for')),
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
            CoversRelationManager::class,
            RingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeys::route('/'),
            'create' => CreateKey::route('/create'),
            'view' => ViewKey::route('/{record}'),
            'edit' => EditKey::route('/{record}/edit'),
        ];
    }
}
