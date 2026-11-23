<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Pages;

use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use App\Models\Team;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListIssuedMerchandises extends ListRecords
{
    protected static string $resource = IssuedMerchandiseResource::class;

    protected function getHeaderActions(): array
    {
        $canRecordGift = $this->teamHasVariants();

        return [
            CreateAction::make()
                ->label(__('Record gift'))
                ->disabled(! $canRecordGift)
                ->tooltip($canRecordGift
                    ? null
                    : __('Add a catalogue item with at least one variant before recording a gift.')),
        ];
    }

    private function teamHasVariants(): bool
    {
        $team = Filament::getTenant();

        return $team instanceof Team && $team->hasMerchandiseVariants();
    }
}
