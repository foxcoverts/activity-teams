<?php

namespace App\Filament\Support\Forms;

use App\Filament\Support\Person;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

class PersonSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->native(false)
            ->allowHtml()
            ->searchable()
            ->getOptionLabelFromRecordUsing(function (Model $record): ?string {
                $person = Person::resolve($record);

                return $person instanceof User
                    ? Person::optionLabel($person)
                    : null;
            })
            ->getOptionLabelUsing(function (?string $value): ?string {
                $person = Person::resolveId($value);

                return $person instanceof User
                    ? Person::optionLabel($person)
                    : null;
            });
    }
}
