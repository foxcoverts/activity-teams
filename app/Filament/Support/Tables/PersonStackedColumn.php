<?php

namespace App\Filament\Support\Tables;

use App\Filament\Support\Concerns\HasPersonDisplay;
use App\Filament\Support\Concerns\HasPersonStack;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

class PersonStackedColumn extends Column implements HasEmbeddedView
{
    use HasPersonDisplay;
    use HasPersonStack {
        HasPersonStack::memberUrl insteadof HasPersonDisplay;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultPersonSize = 32;

        $this->disabledClick(fn (): bool => $this->resolvePeople()->isEmpty());

        $this->url(function (): ?string {
            $people = $this->resolvePeople();

            if ($people->count() !== 1) {
                return null;
            }

            return $this->resolvePersonUrl($people->first());
        });

        $this->action($this->personStackAction());
    }

    public function toEmbeddedHtml(): string
    {
        $people = $this->resolvePeople();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-ta-person-stack',
                'fi-inline' => $this->isInline(),
                ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
            ]);

        if ($people->isEmpty()) {
            $placeholder = $this->getPlaceholder();

            if (blank($placeholder)) {
                return '<div '.$attributes->toHtml().'></div>';
            }

            ob_start(); ?>

            <div <?= $attributes->toHtml() ?>>
                <p class="fi-ta-placeholder">
                    <?= e($placeholder) ?>
                </p>
            </div>

            <?php return (string) ob_get_clean();
        }

        if ($people->count() === 1) {
            return $this->toSinglePersonHtml($people->first());
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

        <?php return (string) ob_get_clean();
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
        $avatarUrl = $this->personAvatarUrl($user);
        $heading = $this->personHeading($user);
        $description = $this->getPersonDescription();
        $showHeading = $this->shouldRenderHeading();
        $headingClasses = implode(' ', $this->headingVisibilityClasses());
        $tooltip = $this->personTooltip($heading);
        $avatar = $this->personAvatarAttributes();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-ta-person',
                'fi-inline' => $this->isInline(),
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

        // Filament wraps the column in an <a> when url() is set — do not nest links here.
        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <?php if (filled($avatarUrl)) { ?>
                <img
                    src="<?= e($avatarUrl) ?>"
                    alt="<?= e($heading) ?>"
                    class="<?= e($avatar['class']) ?>"
                    style="<?= e($avatar['style']) ?>"
                    <?php if (filled($avatar['expandFrom'])) { ?>
                        data-size-from="<?= e($avatar['expandFrom']) ?>"
                    <?php } ?>
                />
            <?php } ?>

            <?php if ($showHeading) { ?>
                <div class="<?= e(trim('min-w-0 '.$headingClasses)) ?>">
                    <span class="fi-ta-person-heading">
                        <?= e($heading) ?>
                    </span>

                    <?php if (filled($description)) { ?>
                        <div class="fi-ta-person-description text-sm text-gray-500 dark:text-gray-400">
                            <?= $description instanceof Htmlable ? $description->toHtml() : e($description) ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php return (string) ob_get_clean();
    }
}
