<?php

namespace App\Enums\Incident;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Female = 'Female';
    case Indeterminate = 'Indeterminate';
    case Male = 'Male';
    case Unknown = 'Unknown';

    public function getLabel(): string
    {
        return __($this->value);
    }
}
