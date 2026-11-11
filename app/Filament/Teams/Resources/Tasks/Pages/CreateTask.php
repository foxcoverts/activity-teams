<?php

namespace App\Filament\Teams\Resources\Tasks\Pages;

use App\Actions\SaveTaskDetailsAction;
use App\Enums\TaskStatus;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected ?string $assigneeIdForCreate = null;

    protected bool $inviteAssigneeForCreate = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $status = TaskStatus::tryFrom((string) ($data['status'] ?? ''));

        if ($status !== null && $status !== TaskStatus::NeedsAction) {
            throw ValidationException::withMessages([
                'status' => __('A new task starts as not started.'),
            ]);
        }

        unset($data['status'], $data['started_at'], $data['completed_at']);

        [$data, $this->assigneeIdForCreate, $this->inviteAssigneeForCreate] = TaskForm::extractAssigneeId($data);

        return TaskForm::mergeVenueData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $team = Filament::getTenant();
        abort_unless($team instanceof Team, 404);

        return app(SaveTaskDetailsAction::class)->create(
            $data,
            $team,
            $this->assigneeIdForCreate,
            invite: $this->inviteAssigneeForCreate,
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
