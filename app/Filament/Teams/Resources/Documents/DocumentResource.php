<?php

namespace App\Filament\Teams\Resources\Documents;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Documents\Pages\CreateDocument;
use App\Filament\Teams\Resources\Documents\Pages\EditDocument;
use App\Filament\Teams\Resources\Documents\Pages\ListDocuments;
use App\Filament\Teams\Resources\Documents\Pages\ViewDocument;
use App\Filament\Teams\Resources\Documents\Schemas\DocumentForm;
use App\Filament\Teams\Resources\Documents\Schemas\DocumentInfolist;
use App\Filament\Teams\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'document';

    protected static ?string $pluralModelLabel = 'documents';

    protected static ?string $slug = 'documents';

    public static function form(Schema $schema): Schema
    {
        return DocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentInfolist::configure($schema);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['team', 'uploader']);

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
