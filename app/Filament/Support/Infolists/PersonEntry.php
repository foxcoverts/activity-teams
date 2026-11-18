<?php

namespace App\Filament\Support\Infolists;

use App\Filament\Support\Concerns\HasPersonDisplay;
use App\Models\User;
use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;

use function Filament\Support\generate_href_html;

class PersonEntry extends Entry implements HasEmbeddedView
{
    use HasPersonDisplay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultPersonSize = 40;
    }

    public function toEmbeddedHtml(): string
    {
        $user = $this->resolvePerson();
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()
            ->class([
                'fi-in-person',
                ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
            ]);

        if (! $user instanceof User) {
            $placeholder = $this->getPlaceholder();

            if ((! $this->shouldRenderHeading()) || blank($placeholder)) {
                return $this->wrapEmbeddedHtml('<div '.$attributes->toHtml().'></div>');
            }

            ob_start(); ?>

            <div <?= $attributes->toHtml() ?>>
                <p class="fi-in-placeholder <?= e(implode(' ', $this->headingVisibilityClasses())) ?>">
                    <?= e($placeholder) ?>
                </p>
            </div>

            <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
        }

        $size = $this->getPersonSize();
        $avatarUrl = $this->personAvatarUrl($user);
        $heading = $this->personHeading($user);
        $description = $this->getPersonDescription();
        $url = $this->getUrl();
        $shouldOpenUrlInNewTab = $this->shouldOpenUrlInNewTab();
        $showHeading = $this->shouldRenderHeading();
        $headingClasses = implode(' ', $this->headingVisibilityClasses());
        $tooltip = $this->personTooltip($heading);
        // When url() is set, Filament wraps the entry content in an <a> — do not nest links.
        $linkInside = filled($url) && $this->hasStateBasedUrls();

        $attributes = $attributes->merge([
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

        <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
    }
}
