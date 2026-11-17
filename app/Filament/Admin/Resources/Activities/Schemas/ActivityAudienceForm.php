<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Filament\Qualifications\QualificationSchema;
use App\Models\Audience;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Support\Collection;

class ActivityAudienceForm
{
    /**
     * @param  Closure(): array<string, string>|null  $options
     * @return array{0: Select, 1: Repeater}
     */
    public static function rowSchema(?Closure $options = null, bool $inRepeater = true): array
    {
        $select = Select::make('audience_id')
            ->label(__('Audience'))
            ->options($options ?? fn (): array => Audience::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->preload()
            ->required()
            ->distinct();

        if ($inRepeater) {
            $select->disableOptionsWhenSelectedInSiblingRepeaterItems();
        }

        return [
            $select,
            QualificationSchema::requirementRepeater(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $excludeAudienceIds
     * @return array<string, string>
     */
    public static function optionsExcluding(Collection $excludeAudienceIds, mixed $keepAudienceId = null): array
    {
        if ($keepAudienceId !== null) {
            $excludeAudienceIds = $excludeAudienceIds->reject(
                fn (mixed $id): bool => (string) $id === (string) $keepAudienceId,
            );
        }

        return Audience::query()
            ->when(
                $excludeAudienceIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $excludeAudienceIds),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
