<?php

namespace App\Filament\Admin\Resources\KeyRings\RelationManagers;

use App\Filament\Admin\Resources\Keys\KeyResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class KeysRelationManager extends RelationManager
{
    protected static string $relationship = 'keys';

    protected static ?string $relatedResource = KeyResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->searchable(false);
    }
}
