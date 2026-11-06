<?php

namespace App\Filament\Teams\Resources\NewsPosts\Tables;

use App\Filament\Teams\Resources\NewsPosts\Filters\NewsPostStatusFilter;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NewsPostsTable
{
    public static function configure(Table $table): Table
    {
        $isManager = function (): bool {
            $user = auth()->user();
            $team = Filament::getTenant();

            return $team instanceof Team && $user?->manages($team);
        };

        return $table
            ->defaultSort('published_at', 'desc')
            ->filters([
                NewsPostStatusFilter::make()
                    ->visible($isManager),
                TrashedFilter::make()
                    ->visible($isManager),
            ]);
    }
}
