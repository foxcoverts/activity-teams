<?php

namespace App\Filament\Teams\Resources\NewsPosts\Pages;

use App\Filament\Teams\Resources\NewsPosts\Actions\PublishAction;
use App\Filament\Teams\Resources\NewsPosts\Actions\RepublishAction;
use App\Filament\Teams\Resources\NewsPosts\Actions\ScheduleAction;
use App\Filament\Teams\Resources\NewsPosts\Actions\UnpublishAction;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Models\NewsPost;
use App\Models\User;
use App\Support\MarkSubjectInboxRead;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsPost extends ViewRecord
{
    protected static string $resource = NewsPostResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $user = auth()->user();
        $post = $this->getRecord();

        if ($user instanceof User && $post instanceof NewsPost) {
            app(MarkSubjectInboxRead::class)($user, $post);
        }
    }

    public function getTitle(): string
    {
        return $this->getRecordTitle();
    }

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
