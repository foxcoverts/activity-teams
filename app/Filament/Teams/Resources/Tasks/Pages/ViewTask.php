<?php

namespace App\Filament\Teams\Resources\Tasks\Pages;

use App\Enums\TimelineAudience;
use App\Filament\Concerns\TogglesTimelineAudience;
use App\Filament\Tasks\Concerns\TogglesTaskCompletion;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Teams\Resources\Tasks\TaskStatusActions;
use App\Filament\Teams\Resources\Tasks\TaskTimeline;
use App\Livewire\Comments;
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
    use TogglesTimelineAudience;

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
                        Livewire::make(Comments::class, [
                            'record' => $record,
                            'highlightId' => is_string(request()->query('comment'))
                                ? request()->query('comment')
                                : null,
                        ])->visible(function (): bool {
                            $task = $this->getRecord();
                            $viewer = auth()->user();

                            return $task instanceof Task
                                && $viewer instanceof User
                                && Comments::shouldShowPanel($task, $viewer);
                        }),
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
        $record = $this->getRecord();
        $viewer = auth()->user();
        $manages = $record instanceof Task
            && $viewer instanceof User
            && $record->team !== null
            && $viewer->manages($record->team);

        $audience = $manages && ! $this->timelineAsMember
            ? TimelineAudience::Manager
            : TimelineAudience::Member;

        return $schema
            ->record($record)
            ->components([
                TaskTimeline::section(
                    audience: $audience,
                    showAudienceToggle: $manages,
                    livewire: $this,
                    task: $record instanceof Task ? $record : null,
                    viewer: $viewer instanceof User ? $viewer : null,
                ),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            TaskStatusActions::group(),
        ];
    }
}
