<?php

namespace App\Filament\Manage\Resources\NewsPosts\Pages;

use App\Filament\Manage\Resources\NewsPosts\Actions\PublishAction;
use App\Filament\Manage\Resources\NewsPosts\Actions\RepublishAction;
use App\Filament\Manage\Resources\NewsPosts\Actions\ScheduleAction;
use App\Filament\Manage\Resources\NewsPosts\Actions\UnpublishAction;
use App\Filament\Manage\Resources\NewsPosts\NewsPostResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsPost extends ViewRecord
{
    protected static string $resource = NewsPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            ScheduleAction::make(),
            PublishAction::make(),
            RepublishAction::make(),
            UnpublishAction::make(),
        ];
    }
}
