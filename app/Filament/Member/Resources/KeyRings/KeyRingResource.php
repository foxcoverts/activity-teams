<?php

namespace App\Filament\Member\Resources\KeyRings;

use App\Filament\Member\Resources\KeyRings\Pages\ListKeyRings;
use App\Filament\Member\Resources\KeyRings\Pages\ViewKeyRing;
use App\Models\KeyRing;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KeyRingResource extends Resource
{
    protected static ?string $model = KeyRing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Key rings';

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
            ->with('keys.covers')
            ->orderBy('name');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
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
                    ->label(__('Key ring')),
                TextColumn::make('keys.name')
                    ->label(__('Keys'))
                    ->listWithLineBreaks(),
            ])
            ->emptyStateHeading(__('No key rings recorded.'))
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKeyRings::route('/'),
            'view' => ViewKeyRing::route('/{record}'),
        ];
    }
}
