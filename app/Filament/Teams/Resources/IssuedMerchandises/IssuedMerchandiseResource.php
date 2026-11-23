<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\CreateIssuedMerchandise;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\EditIssuedMerchandise;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\ListIssuedMerchandises;
use App\Filament\Teams\Resources\IssuedMerchandises\Pages\ViewIssuedMerchandise;
use App\Filament\Teams\Resources\IssuedMerchandises\Schemas\IssuedMerchandiseForm;
use App\Filament\Teams\Resources\IssuedMerchandises\Schemas\IssuedMerchandiseInfolist;
use App\Filament\Teams\Resources\IssuedMerchandises\Tables\IssuedMerchandisesTable;
use App\Models\IssuedMerchandise;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssuedMerchandiseResource extends Resource
{
    protected static ?string $model = IssuedMerchandise::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Merchandise;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Issued';

    protected static ?string $modelLabel = 'issued item';

    protected static ?string $pluralModelLabel = 'issued items';

    protected static ?string $slug = 'issued';

    public static function form(Schema $schema): Schema
    {
        return IssuedMerchandiseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IssuedMerchandiseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IssuedMerchandisesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['user', 'variant.item']);

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIssuedMerchandises::route('/'),
            'create' => CreateIssuedMerchandise::route('/create'),
            'view' => ViewIssuedMerchandise::route('/{record}'),
            'edit' => EditIssuedMerchandise::route('/{record}/edit'),
        ];
    }
}
