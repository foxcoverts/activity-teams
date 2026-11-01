<?php

namespace App\Filament\Member\Resources\NewsPosts;

use App\Filament\Member\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Member\Resources\NewsPosts\Pages\ViewNewsPost;
use App\Models\NewsPost;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsPostResource extends Resource
{
    protected static ?string $model = NewsPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'News';

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'News';

    protected static ?string $slug = 'news';

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
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['team', 'author'])
            ->when(
                $user instanceof User,
                fn (Builder $query): Builder => $query->forUserFeeds($user),
                fn (Builder $query): Builder => $query->whereRaw('0 = 1'),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsPosts::route('/'),
            'team' => ListNewsPosts::route('/{team}'),
            'view' => ViewNewsPost::route('/{team}/{record}'),
        ];
    }
}
