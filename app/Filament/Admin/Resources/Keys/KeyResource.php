<?php

namespace App\Filament\Admin\Resources\Keys;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\EditKey;
use App\Filament\Admin\Resources\Keys\Pages\ListKeys;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Keys\RelationManagers\LocksRelationManager;
use App\Models\Key;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Inventory;

    protected static ?int $navigationSort = 1;

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
                Section::make(__('Key'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                    ]),
                Section::make(__('Holder'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('holder_id')
                            ->hiddenLabel()
                            ->relationship(
                                'holder',
                                'name',
                                function (Builder $query, ?Key $record): Builder {
                                    return $query
                                        ->withTrashed()
                                        ->where(function (Builder $holders) use ($record): void {
                                            $holders->whereNull($holders->getModel()->getQualifiedDeletedAtColumn());

                                            if ($record instanceof Key && filled($record->holder_id)) {
                                                $holders->orWhereKey($record->holder_id);
                                            }
                                        });
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder(__('In the cupboard'))
                            ->nullable()
                            ->rule(fn (?Key $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (blank($value)) {
                                    return;
                                }

                                if ($record instanceof Key && (string) $record->holder_id === (string) $value) {
                                    return;
                                }

                                if (User::query()->whereKey($value)->doesntExist()) {
                                    $fail(User::leftRecordMessage());
                                }
                            }),
                    ]),
                Section::make(__('Unlocks'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('locks')
                            ->hiddenLabel()
                            ->relationship('locks', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
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
                Section::make(__('Holder'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('holder_name')
                            ->hiddenLabel()
                            ->state(fn (Key $record): string => $record->holderName()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
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

    public static function getRelations(): array
    {
        return [
            LocksRelationManager::class,
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
