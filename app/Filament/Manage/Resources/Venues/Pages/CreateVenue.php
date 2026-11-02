<?php

namespace App\Filament\Manage\Resources\Venues\Pages;

use App\Filament\Manage\Resources\Venues\VenueResource;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\Team;
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
            ->label(fn (): string => filled($this->data['similar_venue_id'] ?? null)
                ? __('Use venue')
                : __('Create venue'));
    }

    protected function beforeCreate(): void
    {
        VenueLocationSchema::validateCatalogNameForCreate($this);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $team = VenueResource::currentTeam();
        abort_unless($team instanceof Team, 404);

        $similarVenueId = $data['similar_venue_id'] ?? null;

        if (is_string($similarVenueId) && $similarVenueId !== '') {
            $venue = Venue::query()->findOrFail($similarVenueId);
        } else {
            $venue = Venue::create(Arr::only($data, VenueLocationSchema::venueAttributes(includeNotes: true)));
        }

        $team->addVenue($venue);

        return $venue;
    }
}
