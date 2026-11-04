<?php

namespace App\Filament\Member\Resources\NewsPosts\Pages;

use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class ListNewsPosts extends Page
{
    use WithPagination;

    protected static string $resource = NewsPostResource::class;

    protected static ?string $title = 'News';

    public function mount(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }

    public function getBreadcrumb(): ?string
    {
        return __('filament-panels::resources/pages/list-records.breadcrumb');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.member.resources.news-posts.feed')
                    ->columnSpanFull(),
            ]);
    }

    public function posts(): LengthAwarePaginator
    {
        return once(function (): LengthAwarePaginator {
            $user = auth()->user();
            assert($user instanceof User);

            return NewsPost::query()
                ->with(['team', 'author'])
                ->forUserFeeds($user)
                ->paginate(10);
        });
    }
}
