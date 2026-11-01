<?php

namespace App\Filament\Member\Resources\Documents;

use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Documents\Pages\ListDocuments;
use App\Filament\Member\Resources\Documents\Tables\DocumentsTable;
use App\Models\Document;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    protected static bool $isScopedToTenant = false;

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

    public static function table(Table $table): Table
    {
        return DocumentsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['team'])
            ->when(
                $user instanceof User,
                fn (Builder $query): Builder => $query->forUser($user),
                fn (Builder $query): Builder => $query->whereRaw('0 = 1'),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
        ];
    }
}
