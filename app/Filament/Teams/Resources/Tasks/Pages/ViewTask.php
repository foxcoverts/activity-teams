<?php

namespace App\Filament\Teams\Resources\Tasks\Pages;

use App\Filament\Tasks\Concerns\TogglesTaskCompletion;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Livewire\TaskRelatedBookings;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;

class ViewTask extends ViewRecord
{
    use TogglesTaskCompletion;

    protected static string $resource = TaskResource::class;

    public function content(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $user = auth()->user();
        $sidebar = [];

        if ($record instanceof Task && $user instanceof User && TaskRelatedBookings::shouldShowPanel($record, $user)) {
            $sidebar[] = Livewire::make(TaskRelatedBookings::class, [
                'record' => $record,
            ]);
        }

        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 5])
                ->schema([
                    Group::make([
                        SchemaView::make('filament.tasks.view-heading')
                            ->viewData([
                                'task' => $record,
                                'canToggle' => $record instanceof Task
                                    && $user instanceof User
                                    && $user->can('complete', $record),
                            ]),
                        $this->getInfolistContentComponent(),
                    ])
                        ->columnSpan(['default' => 1, 'lg' => 3]),
                    Group::make($sidebar)
                        ->extraAttributes(['class' => 'invite-panel'])
                        ->columnSpan(['default' => 1, 'lg' => 2])
                        ->visible(fn (): bool => $sidebar !== []),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
