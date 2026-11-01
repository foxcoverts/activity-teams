<?php

namespace App\Filament\Member\Resources\NewsPosts\Pages;

use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\WithPagination;

class ListNewsPosts extends Page
{
    use WithPagination;

    protected static string $resource = NewsPostResource::class;

    protected static ?string $title = 'News';

    public ?Team $team = null;

    public function mount(?Team $team = null): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);

        if ($team instanceof Team) {
            $user = auth()->user();
            abort_unless($user instanceof User && $user->isCurrentMemberOf($team), 404);
        }

        $this->team = $team;
    }

    public function getTitle(): string
    {
        if ($this->team instanceof Team) {
            return __(':team News', ['team' => $this->team->name]);
        }

        return __('News');
    }

    public function getBreadcrumb(): ?string
    {
        return __('filament-panels::resources/pages/list-records.breadcrumb');
    }

    /**
     * @return array<string, string>
     */
    public function getResourceBreadcrumbs(): array
    {
        $breadcrumbs = parent::getResourceBreadcrumbs();

        if ($this->team instanceof Team) {
            $breadcrumbs[static::getResource()::getUrl('team', ['team' => $this->team])] = $this->team->name;
        }

        return $breadcrumbs;
    }

    public function getResourceUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = true): string
    {
        $name ??= 'index';

        if ($name === 'index') {
            unset($parameters['team']);

            return parent::getResourceUrl($name, $parameters, $isAbsolute, $panel, $tenant, shouldGuessMissingParameters: false);
        }

        return parent::getResourceUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
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

            $query = NewsPost::query()
                ->with(['team', 'author'])
                ->forUserFeeds($user);

            if ($this->team instanceof Team) {
                $query->forTeam($this->team);
            }

            return $query->paginate(10);
        });
    }

    public function allNewsUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @return Collection<int, Team>
     */
    public function teams(): Collection
    {
        return once(function (): Collection {
            $user = auth()->user();
            assert($user instanceof User);

            return $user->teams()
                ->where(function (Builder $query): void {
                    $query->whereHas('newsPosts', fn (Builder $newsPosts) => $newsPosts->live());

                    if ($this->team instanceof Team) {
                        $query->orWhereKey($this->team->getKey());
                    }
                })
                ->orderBy('name')
                ->get();
        });
    }

    public function teamUrl(Team $team): string
    {
        return static::getResource()::getUrl('team', ['team' => $team]);
    }

    public function clearTeamFilter(): void
    {
        $this->redirect($this->allNewsUrl());
    }
}
