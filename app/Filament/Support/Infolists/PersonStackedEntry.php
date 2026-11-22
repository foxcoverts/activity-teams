<?php

namespace App\Filament\Support\Infolists;

use App\Filament\Support\Concerns\HasPersonDisplay;
use App\Filament\Support\Concerns\HasPersonStack;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

use function Filament\Support\generate_href_html;

class PersonStackedEntry extends Entry implements HasEmbeddedView
{
    use HasPersonDisplay;
    use HasPersonStack {
        HasPersonStack::memberUrl insteadof HasPersonDisplay;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultPersonSize = 40;

        $this->url(function (): ?string {
            $people = $this->resolvePeople();

            if ($people->count() !== 1) {
                return null;
            }

            return $this->resolvePersonUrl($people->first());
        });

        $this->action($this->personStackAction());
    }

    public function getAction(string|array|null $name = null): ?Action
    {
        if ($name === null && $this->resolvePeople()->count() < 2) {
            return null;
        }

        return parent::getAction($name);
    }

    public function toEmbeddedHtml(): string
    {
        $people = $this->resolvePeople();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-in-person-stack',
                ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
            ]);

        if ($people->isEmpty()) {
            $placeholder = $this->getPlaceholder();

            if (blank($placeholder)) {
                return $this->wrapEmbeddedHtml('<div '.$attributes->toHtml().'></div>');
            }

            ob_start(); ?>

            <div <?= $attributes->toHtml() ?>>
                <p class="fi-in-placeholder">
                    <?= e($placeholder) ?>
                </p>
            </div>

            <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
        }

        if ($people->count() === 1) {
            return $this->wrapEmbeddedHtml($this->toSinglePersonHtml($people->first()));
        }

        $limit = $this->getLimit() ?? $people->count();
        $overflow = ($limit && $people->count() > $limit)
            ? ($people->count() - $limit)
            : 0;
        $visible = $overflow > 0
            ? $people->take($limit)
            : $people;

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?= view('filament.support.person-stack-faces', [
                'people' => $this->personStackItems($visible),
                'overflow' => $overflow,
                'size' => $this->getPersonSize(),
                'ring' => $this->getRing(),
                'overlap' => $this->getOverlap() ?? 2,
            ])->render() ?>
        </div>

        <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
    }

    protected function personStackAction(): Action
    {
        $actionName = 'personStack_'.str($this->getName())->replace('.', '_');

        return Action::make($actionName)
            ->modalHeading(fn (): string => (string) ($this->getLabel() ?? __('People')))
            ->modalContent(function (Model $record): View {
                $people = $this->resolvePeopleFrom($record);

                return view('filament.support.person-stack-list', [
                    'people' => $this->personStackItems($people, $record),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    protected function toSinglePersonHtml(User $user): string
    {
        $size = $this->getPersonSize();
        $avatarUrl = $this->personAvatarUrl($user);
        $heading = $this->personHeading($user);
        $description = $this->getPersonDescription();
        $url = $this->getUrl();
        $shouldOpenUrlInNewTab = $this->shouldOpenUrlInNewTab();
        $showHeading = $this->shouldRenderHeading();
        $headingClasses = implode(' ', $this->headingVisibilityClasses());
        $tooltip = $this->personTooltip($heading);
        $linkInside = filled($url) && $this->hasStateBasedUrls();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-in-person',
                ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
            ])
            ->merge([
                'x-tooltip' => filled($tooltip)
                    ? '{
                        content: '.Js::from($tooltip).',
                        theme: $store.theme,
                        allowHTML: '.Js::from($tooltip instanceof Htmlable).',
                    }'
                    : null,
            ], escape: false);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <div class="flex items-center gap-3">
                <?php if (filled($avatarUrl)) { ?>
                    <?php if ($linkInside) { ?>
                        <a <?= generate_href_html($url, $shouldOpenUrlInNewTab)->toHtml() ?> class="shrink-0">
                            <img
                                src="<?= e($avatarUrl) ?>"
                                alt="<?= e($heading) ?>"
                                class="fi-avatar fi-circular"
                                style="height: <?= e($size) ?>px; width: <?= e($size) ?>px; max-width: none"
                            />
                        </a>
                    <?php } else { ?>
                        <img
                            src="<?= e($avatarUrl) ?>"
                            alt="<?= e($heading) ?>"
                            class="fi-avatar fi-circular shrink-0"
                            style="height: <?= e($size) ?>px; width: <?= e($size) ?>px; max-width: none"
                        />
                    <?php } ?>
                <?php } ?>

                <?php if ($showHeading) { ?>
                    <div class="<?= e(trim('min-w-0 '.$headingClasses)) ?>">
                        <?php if ($linkInside) { ?>
                            <a <?= generate_href_html($url, $shouldOpenUrlInNewTab)->toHtml() ?> class="fi-in-person-heading">
                                <?= e($heading) ?>
                            </a>
                        <?php } else { ?>
                            <span class="fi-in-person-heading">
                                <?= e($heading) ?>
                            </span>
                        <?php } ?>

                        <?php if (filled($description)) { ?>
                            <div class="fi-in-person-description text-sm text-gray-500 dark:text-gray-400">
                                <?= $description instanceof Htmlable ? $description->toHtml() : e($description) ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <?php return (string) ob_get_clean();
    }
}
