<?php

namespace App\Filament\Admin\Resources\Locks\RelationManagers;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CoversRelationManager extends RelationManager
{
    protected static string $relationship = 'covers';

    protected static ?string $relatedResource = LockResource::class;

    protected static ?string $title = 'Covers';

    public function table(Table $table): Table
    {
        return $table
            ->searchable(false);
    }

    protected static ?bool $isBadgeLazyLoaded = true;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->covers()->count();

        return $count > 0 ? (string) $count : null;
    }
}
