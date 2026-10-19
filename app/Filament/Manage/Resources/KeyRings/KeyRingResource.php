<?php

namespace App\Filament\Manage\Resources\KeyRings;

use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\KeyRings\Pages\CreateKeyRing;
use App\Filament\Manage\Resources\KeyRings\Pages\EditKeyRing;
use App\Filament\Manage\Resources\KeyRings\Pages\ListKeyRings;
use App\Filament\Manage\Resources\KeyRings\Pages\ViewKeyRing;
use App\Models\KeyRing;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KeyRingResource extends Resource
{
    protected static ?string $model = KeyRing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Key rings';

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
                Select::make('holder_id')
                    ->label(__('Holder'))
                    ->relationship('holder', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder(__('In the cupboard')),
                Select::make('keys')
                    ->relationship('keys', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('holder_name')
                    ->label(__('Holder'))
                    ->state(fn (KeyRing $record): string => $record->holderName()),
                TextEntry::make('key_descriptions')
                    ->label(__('Keys'))
                    ->state(fn (KeyRing $record): array => $record->keyDescriptions() ?: [__('No keys on this ring.')])
                    ->listWithLineBreaks(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('holder.name')
                    ->label(__('Holder'))
                    ->placeholder(__('In the cupboard')),
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
            'index' => ListKeyRings::route('/'),
            'create' => CreateKeyRing::route('/create'),
            'view' => ViewKeyRing::route('/{record}'),
            'edit' => EditKeyRing::route('/{record}/edit'),
        ];
    }
}
