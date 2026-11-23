<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Pages;

use App\Enums\IssuedMerchandiseSource;
use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateIssuedMerchandise extends CreateRecord
{
    protected static string $resource = IssuedMerchandiseResource::class;

    public function mount(): void
    {
        parent::mount();

        $team = Filament::getTenant();

        if ($team instanceof Team && $team->hasMerchandiseVariants()) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(__('No variants to issue'))
            ->body(__('Add a catalogue item with at least one variant before recording a gift.'))
            ->send();

        $this->redirect(IssuedMerchandiseResource::getUrl('index'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()?->getKey();
        $data['source'] = IssuedMerchandiseSource::Gift;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
