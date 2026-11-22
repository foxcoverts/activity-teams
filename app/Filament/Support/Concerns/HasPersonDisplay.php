<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\Person;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

trait HasPersonDisplay
{
    protected int|Closure|null $personSize = null;

    protected bool $personSizeWasSet = false;

    protected bool|Closure $showHeading = true;

    protected string|Closure|null $headingVisibleFrom = null;

    protected string|Closure|null $headingHiddenFrom = null;

    protected string|Htmlable|Closure|null $personDescription = null;

    protected int $defaultPersonSize = 32;

    protected int $describedPersonSize = 48;

    public function size(int|Closure $size): static
    {
        $this->personSize = $size;
        $this->personSizeWasSet = true;

        return $this;
    }

    public function heading(bool|Closure $condition = true): static
    {
        $this->showHeading = $condition;

        return $this;
    }

    public function headingVisibleFrom(string|Closure|null $breakpoint): static
    {
        $this->headingVisibleFrom = $breakpoint;

        return $this;
    }

    public function headingHiddenFrom(string|Closure|null $breakpoint): static
    {
        $this->headingHiddenFrom = $breakpoint;

        return $this;
    }

    public function description(string|Htmlable|Closure|null $description): static
    {
        $this->personDescription = $description;

        return $this;
    }

    /**
     * Link to the Teams member view when available, otherwise the admin user view
     * when the viewer can open that record.
     */
    public function memberUrl(?Team $team = null): static
    {
        return $this->url(function () use ($team): ?string {
            $user = $this->resolvePerson();

            if (! $user instanceof User) {
                return null;
            }

            return MemberResource::personUrlFor($user, $team ?? Filament::getTenant());
        });
    }

    public function resolvePerson(): ?User
    {
        return Person::resolve(
            $this->getState(),
            $this->getRecord(),
            $this->getRelatedRecord(),
        );
    }

    public function getPersonSize(): int
    {
        if ($this->personSizeWasSet) {
            return (int) $this->evaluate($this->personSize);
        }

        if (filled($this->getPersonDescription())) {
            return $this->describedPersonSize;
        }

        return $this->defaultPersonSize;
    }

    /**
     * When a description enlarges the avatar and the heading is breakpoint-gated,
     * keep the compact size below that breakpoint (avatar-only on small screens).
     */
    public function usesResponsiveDescribedAvatarSize(): bool
    {
        return ! $this->personSizeWasSet
            && filled($this->getPersonDescription())
            && filled($this->getHeadingVisibleFrom());
    }

    /**
     * @return array{class: string, style: string, expandFrom: ?string}
     */
    public function personAvatarAttributes(): array
    {
        if ($this->usesResponsiveDescribedAvatarSize()) {
            return [
                'class' => 'fi-avatar fi-circular fi-person-avatar',
                'style' => sprintf(
                    '--fi-person-avatar-size: %dpx; --fi-person-avatar-size-from: %dpx',
                    $this->defaultPersonSize,
                    $this->describedPersonSize,
                ),
                'expandFrom' => $this->getHeadingVisibleFrom(),
            ];
        }

        $size = $this->getPersonSize();

        return [
            'class' => 'fi-avatar fi-circular',
            'style' => sprintf('height: %dpx; width: %dpx', $size, $size),
            'expandFrom' => null,
        ];
    }

    public function showsHeading(): bool
    {
        return (bool) $this->evaluate($this->showHeading);
    }

    public function getHeadingVisibleFrom(): ?string
    {
        return $this->evaluate($this->headingVisibleFrom);
    }

    public function getHeadingHiddenFrom(): ?string
    {
        return $this->evaluate($this->headingHiddenFrom);
    }

    public function getPersonDescription(): string|Htmlable|null
    {
        $description = $this->evaluate($this->personDescription);

        return filled($description) ? $description : null;
    }

    /**
     * @return list<string>
     */
    protected function headingVisibilityClasses(): array
    {
        $classes = [];

        if (filled($visibleFrom = $this->getHeadingVisibleFrom())) {
            $classes[] = "{$visibleFrom}:fi-visible";
        }

        if (filled($hiddenFrom = $this->getHeadingHiddenFrom())) {
            $classes[] = "{$hiddenFrom}:fi-hidden";
        }

        return $classes;
    }

    protected function shouldRenderHeading(): bool
    {
        if (! $this->showsHeading()) {
            return false;
        }

        return true;
    }

    /**
     * Auto-tooltip the name only when the heading is never shown.
     * Responsive headings keep an alt text on the avatar instead.
     */
    protected function personTooltip(string $heading): string|Htmlable|null
    {
        if (filled($explicit = $this->getTooltip())) {
            return $explicit;
        }

        if (! $this->shouldRenderHeading()) {
            return $heading;
        }

        return null;
    }

    protected function personHeading(User $user): string
    {
        return $user->name;
    }

    protected function personAvatarUrl(User $user): ?string
    {
        return $user->getFilamentAvatarUrl();
    }
}
