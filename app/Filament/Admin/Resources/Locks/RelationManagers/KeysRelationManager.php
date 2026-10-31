<?php

namespace App\Filament\Admin\Resources\Locks\RelationManagers;

use App\Filament\Admin\Resources\Keys\KeyResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KeysRelationManager extends RelationManager
{
    protected static string $relationship = 'keys';

    protected static ?string $relatedResource = KeyResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->searchable(false);
    }

    protected static ?bool $isBadgeLazyLoaded = true;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->keys()->count();

        return $count > 0 ? (string) $count : null;
    }
}
