<?php

namespace App\Filament\Teams\Resources\Tasks\Pages;

use App\Actions\SaveTaskDetailsAction;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Teams\Resources\Tasks\TaskStatusActions;
use App\Models\Task;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            TaskStatusActions::group(includeEdit: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return $record instanceof Task
            ? TaskForm::hydrateTaskFormData($data, $record)
            : $data;
    }

    protected ?string $assigneeIdForSave = null;

    protected bool $inviteAssigneeForSave = true;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$data, $this->assigneeIdForSave, $this->inviteAssigneeForSave] = TaskForm::extractAssigneeId($data);

        return TaskForm::mergeVenueData($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Task, 404);

        return app(SaveTaskDetailsAction::class)->update(
            $record,
            $data,
            $this->assigneeIdForSave,
            syncAssignee: true,
            invite: $this->inviteAssigneeForSave,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
