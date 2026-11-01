<?php

namespace App\Filament\Member\Resources\Keys;

use App\Filament\Keys\PassKeyAction;
use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Keys\Pages\ListKeys;
use App\Filament\Member\Resources\Keys\Pages\ViewKey;
use App\Models\Key;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
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

    protected static ?string $modelLabel = 'key';

    protected static ?string $pluralModelLabel = 'keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(
                $user instanceof User,
                fn (Builder $query) => $query->where('holder_id', $user->getKey()),
                fn (Builder $query) => $query->whereRaw('0 = 1'),
            )
            ->with('locks.covers');
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
                Section::make(__('Unlocks'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('key_descriptions')
                            ->hiddenLabel()
                            ->state(fn (Key $record): array => $record->lockDescriptions() ?: [__('This key opens no locks.')])
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Key')),
                TextColumn::make('locks.name')
                    ->label(__('Unlocks'))
                    ->listWithLineBreaks(),
            ])
            ->emptyStateHeading(__('No keys recorded.'))
            ->recordActions([
                ViewAction::make(),
                PassKeyAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeys::route('/'),
            'view' => ViewKey::route('/{record}'),
        ];
    }
}
