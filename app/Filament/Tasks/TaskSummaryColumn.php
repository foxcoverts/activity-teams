<?php

namespace App\Filament\Tasks;

use App\Models\Task;
use App\Support\Palette;
use Awcodes\BadgeableColumn\Components\Badge;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

final class TaskSummaryColumn
{
    public static function make(): BadgeableColumn
    {
        return BadgeableColumn::make('summary')
            ->label(__('Title'))
            ->searchable()
            ->sortable()
            ->icon(fn (Task $record): ?Heroicon => $record->priority?->getIcon())
            ->iconColor(fn (Task $record): array => $record->priority?->getColor() ?? Palette::neutral())
            ->iconPosition(IconPosition::Before)
            ->tooltip(fn (Task $record): ?string => $record->priority?->getLabel())
            ->suffixBadges([
                Badge::make('overdue')
                    ->label(__('Overdue'))
                    ->color(Palette::destructive())
                    ->visible(fn (Task $record): bool => $record->isOverdue()),
            ])
            ->description(fn (Task $record): ?string => $record->plainNotes(160));
    }
}
