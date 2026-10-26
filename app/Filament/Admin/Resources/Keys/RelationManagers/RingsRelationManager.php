<?php

namespace App\Filament\Admin\Resources\Keys\RelationManagers;

use App\Filament\Admin\Resources\KeyRings\KeyRingResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RingsRelationManager extends RelationManager
{
    protected static string $relationship = 'rings';

    protected static ?string $relatedResource = KeyRingResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->searchable(false);
    }

    protected static ?bool $isBadgeLazyLoaded = true;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->rings()->count();

        return $count > 0 ? (string) $count : null;
    }
}
