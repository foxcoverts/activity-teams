<?php

namespace App\Filament\Forms\Components;

use App\Support\Timezones;
use Filament\Forms\Components\Select;
use Illuminate\Support\Js;

class TimezoneSelect extends Select
{
    public function setUp(): void
    {
        $this->options(Timezones::groups());

        parent::setUp();
    }

    /**
     * When the field still holds $fallback, replace it with the browser timezone once.
     */
    public function detectFromBrowser(?string $fallback = null): static
    {
        $fallback ??= (string) config('app.timezone');

        return $this->extraAlpineAttributes(function () use ($fallback): array {
            $fallbackJs = Js::from($fallback);
            $statePath = Js::from($this->getStatePath());

            return [
                'x-init' => <<<JS
                    \$nextTick(() => {
                        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone
                        if (! tz) {
                            return
                        }

                        const path = {$statePath}
                        if (\$wire.get(path) !== {$fallbackJs}) {
                            return
                        }

                        \$wire.set(path, tz)
                    })
                    JS,
            ];
        }, merge: true);
    }
}
