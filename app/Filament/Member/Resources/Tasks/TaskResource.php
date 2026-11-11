<?php

namespace App\Filament\Member\Resources\Tasks;

use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Resources\Tasks\Pages\ListTasks;
use App\Filament\Teams\Resources\Tasks\TaskResource as TeamsTaskResource;
use App\Models\Task;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'summary';

    protected static ?string $navigationLabel = 'Tasks';

    protected static ?string $modelLabel = 'task';

    protected static ?string $pluralModelLabel = 'tasks';

    protected static ?string $slug = 'tasks';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('viewAny', Task::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['team', 'venue', 'attendance'])
            ->visibleTo($user)
            ->whereHas(
                'attendance',
                fn (Builder $attendance): Builder => $attendance
                    ->where('user_id', $user->getKey())
                    ->whereIn('status', array_map(
                        fn (TaskAttendeeStatus $status): string => $status->value,
                        TaskAttendeeStatus::activeAssignments(),
                    )),
            );
    }

    public static function viewUrl(Task $task): string
    {
        return TeamsTaskResource::getUrl('view', ['record' => $task], panel: 'teams', tenant: $task->team);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->recordUrl(fn (Task $record): string => static::viewUrl($record))
            ->columns([
                ViewColumn::make('complete')
                    ->label('')
                    ->view('filament.tasks.complete-control-column')
                    ->disabledClick()
                    ->alignCenter()
                    ->width('2.5rem'),
                TextColumn::make('summary')
                    ->label(__('Title'))
                    ->searchable()
                    ->sortable()
                    ->html()
                    ->formatStateUsing(fn (?string $state, Task $record): string => view(
                        'filament.tasks.title-with-overdue',
                        [
                            'title' => $state,
                            'overdue' => $record->isOverdue(),
                        ],
                    )->render()),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('due_at')
                    ->label(__('Due'))
                    ->dateTime('j M Y, H:i')
                    ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('team.name')
                    ->badge()
                    ->color(fn (Task $record): array => $record->team?->brandColor()->getColor() ?? Palette::neutral())
                    ->visible(fn (HasTable $livewire): bool => blank($livewire->getTableFilterState('team_id')['value'] ?? null)),
                TextColumn::make('response')
                    ->label(__('Your response'))
                    ->state(fn (Task $record): ?TaskAttendeeStatus => $record->attendance?->status)
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('team_id')
                    ->label(__('Team'))
                    ->options(fn (): array => auth()->user()?->teams()->orderBy('teams.name')->pluck('teams.name', 'teams.id')->all() ?? []),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Task $record): string => static::viewUrl($record)),
            ])
            ->defaultSort('due_at');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
        ];
    }
}
