<?php

namespace App\Filament\Support;

final class SubmitsOnModEnter
{
    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'x-on:keydown.meta.enter.prevent' => '$el.closest(\'form\')?.requestSubmit()',
            'x-on:keydown.ctrl.enter.prevent' => '$el.closest(\'form\')?.requestSubmit()',
        ];
    }
}
