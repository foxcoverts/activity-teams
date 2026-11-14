<?php

namespace App\Filament\Admin\Resources\Teams\Pages;

use App\Enums\TeamFeature;
use App\Filament\Admin\Resources\Teams\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['manager_id']);

        $features = is_array($data['features'] ?? null) ? $data['features'] : [];
        $enabled = (bool) ($features[TeamFeature::Debrief->value] ?? false);

        if ($enabled) {
            $features[TeamFeature::Debrief->value] = [
                'enabled' => true,
                'activated_at' => now()->toIso8601String(),
            ];
        } else {
            unset($features[TeamFeature::Debrief->value]);
        }

        $data['features'] = $features;

        return $data;
    }
}
