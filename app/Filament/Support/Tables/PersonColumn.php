<?php

namespace App\Filament\Support\Tables;

use App\Filament\Support\Concerns\HasPersonDisplay;
use App\Models\User;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;

class PersonColumn extends Column implements HasEmbeddedView
{
    use HasPersonDisplay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultPersonSize = 32;
    }

    public function toEmbeddedHtml(): string
    {
        $user = $this->resolvePerson();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-ta-person',
                'fi-inline' => $this->isInline(),
                ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
            ]);

        if (! $user instanceof User) {
            $placeholder = $this->getPlaceholder();

            if ((! $this->shouldRenderHeading()) || blank($placeholder)) {
                return '<div '.$attributes->toHtml().'></div>';
            }

            ob_start(); ?>

            <div <?= $attributes->toHtml() ?>>
                <p class="fi-ta-placeholder <?= e(implode(' ', $this->headingVisibilityClasses())) ?>">
                    <?= e($placeholder) ?>
                </p>
            </div>

            <?php return (string) ob_get_clean();
        }

        $avatarUrl = $this->personAvatarUrl($user);
        $heading = $this->personHeading($user);
        $description = $this->getPersonDescription();
        $showHeading = $this->shouldRenderHeading();
        $headingClasses = implode(' ', $this->headingVisibilityClasses());
        $tooltip = $this->personTooltip($heading);
        $avatar = $this->personAvatarAttributes();

        $attributes = $attributes->merge([
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
