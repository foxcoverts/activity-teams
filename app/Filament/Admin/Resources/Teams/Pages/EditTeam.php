<?php

namespace App\Filament\Admin\Resources\Teams\Pages;

use App\Enums\TeamFeature;
use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Models\Team;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($record instanceof Team) {
            $data['features'][TeamFeature::Debrief->value] = $record->featureEnabled(TeamFeature::Debrief);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (! $record instanceof Team) {
            return $data;
        }

        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $enabled = (bool) ($features[TeamFeature::Debrief->value] ?? false);
        $data['features'] = $record->featuresWithDebriefToggle($features, $enabled);

        return $data;
    }
}
