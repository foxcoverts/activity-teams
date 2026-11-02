<?php

namespace App\Filament\Admin\Resources\Venues\Pages;

use App\Filament\Admin\Resources\Venues\VenueResource;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateVenue extends CreateRecord
{
    use HasWizard;

    protected static string $resource = VenueResource::class;

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return VenueLocationSchema::creationSteps(includeNotes: true);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(fn (): string => $this->isOpeningExistingVenue()
                ? __('Open existing venue')
                : __('Create venue'));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->isOpeningExistingVenue()
            ? __('Opened the existing venue instead of creating a duplicate')
            : parent::getCreatedNotificationTitle();
    }

    private function isOpeningExistingVenue(): bool
    {
        return filled($this->data['similar_venue_id'] ?? null);
    }

    protected function beforeCreate(): void
    {
        VenueLocationSchema::validateCatalogNameForCreate($this);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['has_map_pin'], $data['map']);

        return Arr::only($data, [
            ...VenueLocationSchema::venueAttributes(includeNotes: true),
            'similar_venue_id',
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $similarVenueId = $data['similar_venue_id'] ?? null;

        if (is_string($similarVenueId) && $similarVenueId !== '') {
            return Venue::query()->findOrFail($similarVenueId);
        }

        return Venue::create(Arr::only($data, VenueLocationSchema::venueAttributes(includeNotes: true)));
    }
}
