<?php

namespace App\Filament\Admin\Resources\Audiences;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Audiences\Pages\CreateAudience;
use App\Filament\Admin\Resources\Audiences\Pages\EditAudience;
use App\Filament\Admin\Resources\Audiences\Pages\ListAudiences;
use App\Filament\Admin\Resources\Audiences\Pages\ViewAudience;
use App\Filament\Admin\Resources\Audiences\Schemas\AudienceForm;
use App\Filament\Admin\Resources\Audiences\Schemas\AudienceInfolist;
use App\Filament\Admin\Resources\Audiences\Tables\AudiencesTable;
use App\Models\Audience;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AudienceResource extends Resource
{
    protected static ?string $model = Audience::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return AudienceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AudienceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AudiencesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudiences::route('/'),
            'create' => CreateAudience::route('/create'),
            'view' => ViewAudience::route('/{record}'),
            'edit' => EditAudience::route('/{record}/edit'),
        ];
    }
}
