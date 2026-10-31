<?php

namespace App\Filament\Admin\Resources\KeyRings;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\KeyRings\Pages\CreateKeyRing;
use App\Filament\Admin\Resources\KeyRings\Pages\EditKeyRing;
use App\Filament\Admin\Resources\KeyRings\Pages\ListKeyRings;
use App\Filament\Admin\Resources\KeyRings\Pages\ViewKeyRing;
use App\Filament\Admin\Resources\KeyRings\RelationManagers\KeysRelationManager;
use App\Models\KeyRing;
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

class KeyRingResource extends Resource
{
    protected static ?string $model = KeyRing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

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
                Section::make(__('Key ring'))
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
                                function (Builder $query, ?KeyRing $record): Builder {
                                    return $query
                                        ->withTrashed()
                                        ->where(function (Builder $holders) use ($record): void {
                                            $holders->whereNull($holders->getModel()->getQualifiedDeletedAtColumn());

                                            if ($record instanceof KeyRing && filled($record->holder_id)) {
                                                $holders->orWhereKey($record->holder_id);
                                            }
                                        });
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder(__('In the cupboard'))
                            ->nullable()
                            ->rule(fn (?KeyRing $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (blank($value)) {
                                    return;
                                }

                                if ($record instanceof KeyRing && (string) $record->holder_id === (string) $value) {
                                    return;
                                }

                                if (User::query()->whereKey($value)->doesntExist()) {
                                    $fail(User::leftRecordMessage());
                                }
                            }),
                    ]),
                Section::make(__('Keys'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('keys')
                            ->hiddenLabel()
                            ->relationship('keys', 'name')
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
                Section::make(__('Key ring'))
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
                            ->state(fn (KeyRing $record): string => $record->holderName()),
                    ]),
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

    public static function getRelations(): array
    {
        return [
            KeysRelationManager::class,
        ];
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
