<?php

namespace App\Filament\Manage\Resources\KeyRings;

use App\Filament\KeyRings\PassKeyRingAction;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\KeyRings\Pages\ListKeyRings;
use App\Filament\Manage\Resources\KeyRings\Pages\ViewKeyRing;
use App\Models\KeyRing;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KeyRingResource extends Resource
{
    protected static ?string $model = KeyRing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $modelLabel = 'key ring';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'holder',
                'keys.covers.requirements.activity',
                'keys.covers.requirements.venue',
                'keys.requirements.activity',
                'keys.requirements.venue',
            ]);
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->relevantTo($team);
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
                        TextEntry::make('holder_name')
                            ->label(__('Holder'))
                            ->state(fn (KeyRing $record): string => $record->holderName()),
                    ]),
                Section::make(__('Keys'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('key_descriptions')
                            ->label(__('Keys'))
                            ->state(fn (KeyRing $record): array => $record->keyDescriptions() ?: [__('No keys on this ring.')])
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                        TextEntry::make('required_for')
                            ->label(__('Required for'))
                            ->state(fn (KeyRing $record): array => $record->requirementSummariesFor(static::currentTeam()))
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
                    ->label(__('Key ring'))
                    ->searchable(),
                TextColumn::make('holder.name')
                    ->label(__('Holder'))
                    ->placeholder(__('In the cupboard')),
                TextColumn::make('keys')
                    ->label(__('Keys'))
                    ->state(fn (KeyRing $record): array => $record->keyNames())
                    ->listWithLineBreaks()
                    ->placeholder('-'),
            ])
            ->recordActions([
                ViewAction::make(),
                PassKeyRingAction::make(),
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

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
