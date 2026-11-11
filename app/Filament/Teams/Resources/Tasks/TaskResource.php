<?php

namespace App\Filament\Teams\Resources\Tasks;

use App\Enums\TeamFeature;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Tasks\Pages\CreateTask;
use App\Filament\Teams\Resources\Tasks\Pages\EditTask;
use App\Filament\Teams\Resources\Tasks\Pages\ListTasks;
use App\Filament\Teams\Resources\Tasks\Pages\ViewTask;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Teams\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Teams\Resources\Tasks\Tables\TasksTable;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'summary';

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Tasks';

    protected static ?string $modelLabel = 'task';

    protected static ?string $pluralModelLabel = 'tasks';

    protected static ?string $slug = 'tasks';

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $tenant = Filament::getTenant();
        if ($tenant instanceof Team) {
            $query->whereBelongsTo($tenant);
        }

        return $query->with(['attendance.user']);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', Task::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('create', Task::class);
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof Task
            && $user instanceof User
            && $user->can('update', $record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $record instanceof Task
            && $user instanceof User
            && $user->can('delete', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    public static function tasksEnabled(): bool
    {
        $team = static::currentTeam();

        return $team instanceof Team && $team->featureEnabled(TeamFeature::Tasks);
    }
}
