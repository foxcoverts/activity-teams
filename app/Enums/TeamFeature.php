<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TeamFeature: string implements HasLabel
{
    case Tasks = 'tasks';
    case Debrief = 'debrief';

    public function getLabel(): string
    {
        return match ($this) {
            self::Tasks => __('Tasks'),
            self::Debrief => __('Debrief'),
        };
    }
}
