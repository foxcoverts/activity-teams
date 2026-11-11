<?php

namespace App\Filament\Teams\Resources\NewsPosts;

use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\NewsPosts\Pages\CreateNewsPost;
use App\Filament\Teams\Resources\NewsPosts\Pages\EditNewsPost;
use App\Filament\Teams\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Teams\Resources\NewsPosts\Pages\ViewNewsPost;
use App\Filament\Teams\Resources\NewsPosts\Schemas\NewsPostForm;
use App\Filament\Teams\Resources\NewsPosts\Schemas\NewsPostInfolist;
use App\Filament\Teams\Resources\NewsPosts\Tables\NewsPostsTable;
use App\Models\NewsPost;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NewsPostResource extends Resource
{
    protected static ?string $model = NewsPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'News';

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'News';

    protected static ?string $slug = 'news';

    public static function form(Schema $schema): Schema
    {
        return NewsPostForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsPostInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsPostsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['team', 'author']);

        $team = Filament::getTenant();
        $user = auth()->user();

        if (! $user?->manages($team)) {
            $query->whereNowOrPast('published_at');
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsPosts::route('/'),
            'create' => CreateNewsPost::route('/create'),
            'view' => ViewNewsPost::route('/{record}'),
            'edit' => EditNewsPost::route('/{record}/edit'),
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
