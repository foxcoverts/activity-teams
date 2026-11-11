<?php

namespace App\Filament\Teams\Resources\Tasks\Pages;

use App\Filament\Tasks\Concerns\TogglesTaskCompletion;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Teams\Resources\Tasks\TaskStatusActions;
use App\Filament\Teams\Resources\Tasks\TaskTimeline;
use App\Livewire\TaskAssigneePanel;
use App\Livewire\TaskRelatedBookings;
use App\Models\Task;
use App\Models\User;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewTask extends ViewRecord
{
    use TogglesTaskCompletion;

    protected static string $resource = TaskResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    public function getHeading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        if (! $record instanceof Task) {
            return parent::getHeading();
        }

        $viewer = auth()->user();

        return view('filament.tasks.view-heading', [
            'task' => $record,
            'canToggle' => $viewer instanceof User && $viewer->can('complete', $record),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $user = auth()->user();
        $sidebar = [];

        if ($record instanceof Task && $user instanceof User && TaskAssigneePanel::shouldShowPanel($record, $user)) {
            $sidebar[] = Livewire::make(TaskAssigneePanel::class, [
                'record' => $record,
            ]);
        }

        if ($record instanceof Task && $user instanceof User && TaskRelatedBookings::shouldShowPanel($record, $user)) {
            $sidebar[] = Livewire::make(TaskRelatedBookings::class, [
                'record' => $record,
            ]);
        }

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 5])
                ->schema([
                    Group::make([
                        $this->getInfolistContentComponent(),
                    ])
                        ->columnSpan(['default' => 1, 'lg' => 3]),
                    Group::make($sidebar)
                        ->extraAttributes(['class' => 'invite-panel'])
                        ->columnSpan(['default' => 1, 'lg' => 2])
                        ->visible(fn (): bool => $sidebar !== []),
                    EmbeddedSchema::make('timeline')
                        ->columnSpan(['default' => 1, 'lg' => 3]),
                ]),
        ]);
    }

    public function timeline(Schema $schema): Schema
    {
        return $schema
            ->record($this->getRecord())
            ->components([
                TaskTimeline::section(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            TaskStatusActions::group(),
        ];
    }
}
