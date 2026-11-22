<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;

trait HasPersonStack
{
    protected int|Closure|null $stackLimit = 3;

    protected int|Closure|null $stackRing = 2;

    protected int|Closure|null $stackOverlap = 2;

    protected ?Closure $personUrlUsing = null;

    protected Team|Closure|null $memberUrlTeam = null;

    protected bool $usesMemberUrl = false;

    public function limit(int|Closure|null $limit = 3): static
    {
        $this->stackLimit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->evaluate($this->stackLimit);
    }

    public function ring(int|Closure|null $ring): static
    {
        $this->stackRing = $ring;

        return $this;
    }

    public function getRing(): ?int
    {
        return $this->evaluate($this->stackRing);
    }

    public function overlap(int|Closure|null $overlap): static
    {
        $this->stackOverlap = $overlap;

        return $this;
    }

    public function getOverlap(): ?int
    {
        return $this->evaluate($this->stackOverlap);
    }

    /**
     * @param  Closure(User $person, mixed $record): (?string)|null  $callback
     */
    public function personUrl(?Closure $callback): static
    {
        $this->personUrlUsing = $callback;
        $this->usesMemberUrl = false;
        $this->memberUrlTeam = null;

        return $this;
    }

    /**
     * Link each person to the Teams member view when available, otherwise the
     * admin user view when the viewer can open that record.
     */
    public function memberUrl(Team|Closure|null $team = null): static
    {
        $this->usesMemberUrl = true;
        $this->memberUrlTeam = $team;
        $this->personUrlUsing = null;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function resolvePeople(): Collection
    {
        // Prefer related models when the column name is dotted (e.g. managers.name):
        // Filament only treats dotted names as relationships for eager-loading /
        // getRelatedRecords(), while getState() becomes attribute values (names).
        $related = method_exists($this, 'getRelatedRecords')
            ? $this->normalizePeople($this->getRelatedRecords())
            : collect();

        if ($related->isNotEmpty()) {
            return $related;
        }

        return $this->normalizePeople($this->getState());
    }

    /**
     * @return Collection<int, User>
     */
    public function resolvePeopleFrom(?Model $record): Collection
    {
        if (! $record instanceof Model) {
            return collect();
        }

        $relationshipName = $this->personStackRelationshipName();

        if ($relationshipName === '' || ! $record->isRelation($relationshipName)) {
            return collect();
        }

        return $this->normalizePeople($record->getRelationValue($relationshipName));
    }

    protected function personStackRelationshipName(): string
    {
        $name = $this->getName();

        if (str($name)->contains('.')) {
            return (string) str($name)->before('.');
        }

        return $name;
    }

    public function resolvePersonUrl(User $user, ?Model $record = null): ?string
    {
        $record ??= $this->getRecord() instanceof Model ? $this->getRecord() : null;

        if ($this->personUrlUsing instanceof Closure) {
            // Call directly so Filament evaluation cannot replace $person with auth.
            return ($this->personUrlUsing)($user, $record);
        }

        if (! $this->usesMemberUrl) {
            return null;
        }

        $resolvedTeam = $this->evaluate($this->memberUrlTeam)
            ?? ($record instanceof Team ? $record : null)
            ?? Filament::getTenant();

        return MemberResource::personUrlFor(
            $user,
            $resolvedTeam instanceof Team ? $resolvedTeam : null,
        );
    }

    /**
     * @param  Collection<int, User>  $people
     * @return list<array{name: string, avatar_url: ?string, url: ?string}>
     */
    public function personStackItems(Collection $people, ?Model $record = null): array
    {
        $record ??= $this->getRecord() instanceof Model ? $this->getRecord() : null;

        return $people
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'avatar_url' => $user->getFilamentAvatarUrl(),
                'url' => $this->resolvePersonUrl($user, $record),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, User>
     */
    protected function normalizePeople(mixed $state): Collection
    {
        if ($state instanceof User) {
            return collect([$state]);
        }

        if ($state instanceof Enumerable) {
            $state = $state->all();
        }

        if (! is_array($state)) {
            return collect();
        }

        return collect($state)
            ->filter(fn (mixed $person): bool => $person instanceof User)
            ->values();
    }
}
