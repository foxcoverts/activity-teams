<?php

namespace App\Filament\Manage\Resources\Keys;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Keys\Pages\CreateKey;
use App\Filament\Manage\Resources\Keys\Pages\EditKey;
use App\Filament\Manage\Resources\Keys\Pages\ListKeys;
use App\Filament\Manage\Resources\Keys\Pages\ViewKey;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KeyResource extends Resource
{
    protected static ?string $model = Key::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Key catalog';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Repeater::make('requirements')
                    ->relationship()
                    ->label(__('Required for'))
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
                    ->columnSpanFull(),
                Select::make('covers')
                    ->label(__('Covers'))
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
                TextEntry::make('ring_list')
                    ->label(__('Key rings'))
                    ->visible(fn (?Model $record): bool => $record instanceof Key)
                    ->state(function (?Model $record): string {
                        if (! $record instanceof Key) {
                            return '';
                        }

                        $lines = $record->ringDescriptions();

                        return implode(', ', $lines);
                    })
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                RepeatableEntry::make('requirements')
                    ->label(__('Required for'))
                    ->placeholder(__('Not required for a session yet.'))
                    ->schema([
                        TextEntry::make('activity.name')
                            ->label(__('Activity')),
                        TextEntry::make('venue.name')
                            ->label(__('Session venue')),
                    ]),
                TextEntry::make('covers.name')
                    ->label(__('Covers'))
                    ->placeholder(__('Covers no other key.'))
                    ->listWithLineBreaks(),
                TextEntry::make('ring_list')
                    ->label(__('Key rings'))
                    ->state(fn (Key $record): array => $record->ringDescriptions())
                    ->listWithLineBreaks(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
