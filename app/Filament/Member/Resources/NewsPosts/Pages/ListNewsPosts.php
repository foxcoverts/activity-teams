<?php

namespace App\Filament\Member\Resources\NewsPosts\Pages;

use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Resources\NewsPosts\Pages\ListNewsPosts as ListTeamsNewsPosts;
use App\Models\NewsPost;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Size;

class ListNewsPosts extends ListRecords
{
    protected static string $resource = NewsPostResource::class;

    protected static ?string $title = 'News';

    protected string $view = 'filament.resources.news-posts.list-news-posts';

    public function teamBadgeAction(): Action
    {
        return Action::make('viewTeam')
            ->label(fn (NewsPost $record) => $record->team->name)
            ->color(fn (NewsPost $record): array => $record->team->brandColor()->getColor())
            ->button()
            ->size(Size::ExtraSmall)
            ->url(fn ($record) => ListTeamsNewsPosts::getUrl([], panel: 'teams', tenant: $record->team));
    }
}
