<?php

namespace App\Filament\Forms\Components;

use App\Support\Timezones;
use Filament\Forms\Components\Select;

class TimezoneSelect extends Select
{
    public function setUp(): void
    {
        $this->options(Timezones::groups());

        parent::setUp();
    }
}
