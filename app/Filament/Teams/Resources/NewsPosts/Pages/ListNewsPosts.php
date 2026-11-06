<?php

namespace App\Filament\Teams\Resources\NewsPosts\Pages;

use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsPosts extends ListRecords
{
    protected static string $resource = NewsPostResource::class;

    protected string $view = 'filament.teams.resources.news-posts.list-news-posts';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
