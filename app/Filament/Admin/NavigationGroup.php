<?php

namespace App\Filament\Admin;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Organisation = 'organisation';
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Mail = 'mail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Organisation => __('Organisation'),
            self::Catalog => __('Catalog'),
            self::Inventory => __('Inventory'),
            self::Mail => __('Mail'),
        };
    }
}
