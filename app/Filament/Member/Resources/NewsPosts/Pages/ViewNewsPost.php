<?php

namespace App\Filament\Member\Resources\NewsPosts\Pages;

use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ViewNewsPost extends ViewRecord
{
    protected static string $resource = NewsPostResource::class;

    public ?Team $team = null;

    public function mount(int|string $record): void
    {
        $team = $this->resolveTeam();
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isCurrentMemberOf($team), 404);

        $this->team = $team;

        parent::mount($record);

        abort_unless((string) $this->getRecord()->team_id === (string) $team->getKey(), 404);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 404);
    }

    protected function resolveRecord(int|string $key): Model
    {
        $team = $this->team ?? $this->resolveTeam();

        $record = NewsPost::query()
            ->with(['team', 'author'])
            ->where('team_id', $team->getKey())
            ->where('slug', $key)
            ->first();

        if (! $record instanceof NewsPost) {
            throw (new ModelNotFoundException)->setModel(NewsPost::class, [$key]);
        }

        return $record;
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
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

        if ($this->team instanceof Team) {
            $parameters['team'] ??= $this->team;
        }

        return parent::getResourceUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    protected function hasInfolist(): bool
    {
        return true;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.member.resources.news-posts.article')
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    private function resolveTeam(): Team
    {
        $team = $this->team ?? request()->route('team');

        if (is_string($team) || is_int($team)) {
            $team = Team::query()->where('slug', $team)->firstOrFail();
        }

        abort_unless($team instanceof Team, 404);

        return $team;
    }
}
