<?php

namespace App\Filament\Teams\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Support\Tables\PersonColumn;
use App\Filament\Tasks\TaskSummaryColumn;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Teams\Resources\Tasks\TaskStatusActions;
use App\Models\Task;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                ViewColumn::make('complete')
                    ->label('')
                    ->view('filament.tasks.complete-control-column')
                    ->disabledClick()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'task-complete-col'])
                    ->extraCellAttributes(['class' => 'task-complete-col'])
                    ->width('2.75rem'),
                TaskSummaryColumn::make(),
                PersonColumn::make('attendance.user.name')
                    ->label(__('To'))
                    ->headingVisibleFrom('md')
                    ->memberUrl()
                    ->grow(false),
                TextColumn::make('due_at')
                    ->label(__('Due'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('venue.name')
                    ->label(__('Venue'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TaskStatus::class)
                    ->multiple()
                    ->default([
                        TaskStatus::NeedsAction->value,
                        TaskStatus::InProcess->value,
                    ]),
                SelectFilter::make('priority')
                    ->options(TaskPriority::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...TaskStatusActions::actions(),
                    DeleteAction::make(),
                ]),
            ])
            ->recordUrl(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record]));
    }
}
