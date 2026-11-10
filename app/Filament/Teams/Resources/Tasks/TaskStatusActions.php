<?php

namespace App\Filament\Teams\Resources\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class TaskStatusActions
{
    public static function group(bool $includeEdit = true): ActionGroup
    {
        $actions = $includeEdit
            ? [EditAction::make(), ...self::actions()]
            : self::actions();

        $actions[] = DeleteAction::make();

        return ActionGroup::make($actions)
            ->label(__('Manage'))
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->button();
    }

    /**
     * @return array<int, Action>
     */
    public static function actions(): array
    {
        return [
            self::start(),
            self::complete(),
            self::reopen(),
            self::cancel(),
        ];
    }

    public static function start(): Action
    {
        return Action::make('startTask')
            ->label(__('Start'))
            ->icon(Heroicon::OutlinedPlay)
            ->color(TaskStatus::InProcess->getColor())
            ->visible(fn (Task $record): bool => $record->status === TaskStatus::NeedsAction)
            ->authorize(fn (Task $record): bool => self::canComplete($record))
            ->action(function (Task $record): void {
                $record->markStarted();
            });
    }

    public static function complete(): Action
    {
        return Action::make('completeTask')
            ->label(__('Complete'))
            ->icon(TaskStatus::Completed->getIcon())
            ->color(TaskStatus::Completed->getColor())
            ->visible(fn (Task $record): bool => ! $record->isComplete() && ! $record->isCancelled())
            ->authorize(fn (Task $record): bool => self::canComplete($record))
            ->action(function (Task $record): void {
                $record->markCompleted();
            });
    }

    public static function reopen(): Action
    {
        return Action::make('reopenTask')
            ->label(__('Reopen'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color(Palette::uncertain())
            ->visible(fn (Task $record): bool => $record->isComplete() || $record->isCancelled())
            ->authorize(fn (Task $record): bool => self::canUpdate($record) || self::canComplete($record))
            ->action(function (Task $record): void {
                $record->reopen();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelTask')
            ->label(__('Cancel'))
            ->icon(TaskStatus::Cancelled->getIcon())
            ->color(TaskStatus::Cancelled->getColor())
            ->requiresConfirmation()
            ->modalHeading(__('Cancel task'))
            ->modalDescription(__('Cancelled tasks stay on the team for history. You can reopen them later, or delete them once cancelled.'))
            ->visible(fn (Task $record): bool => ! $record->isCancelled())
            ->authorize(fn (Task $record): bool => self::canUpdate($record))
            ->action(function (Task $record): void {
                $record->markCancelled();
            });
    }

    private static function canUpdate(Task $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $record);
    }

    private static function canComplete(Task $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('complete', $record);
    }
}
