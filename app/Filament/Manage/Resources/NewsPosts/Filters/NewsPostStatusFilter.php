<?php

namespace App\Filament\Manage\Resources\NewsPosts\Filters;

use App\Enums\NewsPostStatus;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class NewsPostStatusFilter
{
    public static function make(): SelectFilter
    {
        $options = [NewsPostStatus::Draft, NewsPostStatus::Scheduled, NewsPostStatus::Published];

        return SelectFilter::make('status')
            ->label(__('Status'))
            ->placeholder(__('Select status'))
            ->options(array_combine(
                array_map(fn ($status) => $status->value, $options),
                array_map(fn ($status) => $status->getLabel(), $options),
            ))
            ->attribute('published_at')
            ->indicateUsing(function ($data) {
                $status = NewsPostStatus::tryFrom($data['value']);

                if (is_null($status)) {
                    return null;
                }

                return Indicator::make($status->getLabel())
                    ->color($status->getColor());
            })
            ->query(function (Builder $query, $data) {
                return match ($data['value']) {
                    NewsPostStatus::Draft->value => $query->whereNull('published_at'),
                    NewsPostStatus::Scheduled->value => $query->whereFuture('published_at'),
                    NewsPostStatus::Published->value => $query->whereNowOrPast('published_at'),
                    default => $query,
                };
            });
    }
}
