<?php

namespace App\Filament\Teams\Resources\NewsPosts\Tables;

use App\Filament\Teams\Resources\NewsPosts\Filters\NewsPostStatusFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NewsPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->filters([
                NewsPostStatusFilter::make(),
                TrashedFilter::make(),
            ]);
    }
}
