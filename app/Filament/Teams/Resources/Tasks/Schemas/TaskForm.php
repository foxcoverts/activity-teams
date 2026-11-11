<?php

namespace App\Filament\Teams\Resources\Tasks\Schemas;

use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Venues\VenueLocationSchema;
use App\Models\Task;
use App\Models\Team;
use App\Models\Venue;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (?Task $record): string => __(':Status task', [
                    'status' => $record?->status?->getHeadingLabel() ?? __('New'),
                ]))
                    ->icon(fn (?Task $record): Heroicon => ($record?->status ?? TaskStatus::NeedsAction)->getIcon())
                    ->iconColor(fn (?Task $record): array => ($record?->status ?? TaskStatus::NeedsAction)->getColor())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(static::components()),
            ]);
    }

    /**
     * @return list<Component|\Filament\Forms\Components\Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('summary')
                ->label(__('Title'))
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label(__('Notes'))
                ->rows(4),
            Select::make('venue_id')
                ->label(__('Venue'))
                ->options(fn (): array => BookingForm::venueOptions())
                ->default(fn (): ?string => static::defaultVenueId())
                ->selectablePlaceholder(false)
                ->searchable()
                ->live()
                ->required()
                ->afterStateUpdated(function (mixed $state): void {
                    $team = Filament::getTenant();

                    if (! $team instanceof Team || ! is_string($state) || $state === BookingForm::OTHER_VENUE) {
                        return;
                    }

                    $venue = Venue::query()->find($state);

                    if (
                        $venue instanceof Venue
                        && ! $team->venues()->whereKey($venue->getKey())->exists()
                    ) {
                        $team->addVenue($venue);
                    }
                })
                ->createOptionForm(fn (): array => VenueLocationSchema::creationSteps())
                ->createOptionAction(fn (Action $action): Action => $action
                    ->steps(fn (): array => VenueLocationSchema::creationSteps())
                    ->modifyWizardUsing(fn (Wizard $wizard): Wizard => VenueLocationSchema::prepareActionWizard($wizard))
                )
                ->createOptionUsing(function (array $data): string {
                    $team = Filament::getTenant();

                    if (! $team instanceof Team) {
                        throw ValidationException::withMessages([
                            'venue_id' => __('Choose a team.'),
                        ]);
                    }

                    $similarVenueId = $data['similar_venue_id'] ?? null;

                    if (! (is_string($similarVenueId) && $similarVenueId !== '')) {
                        VenueLocationSchema::validateCatalogName($data);
                    }

                    $venue = is_string($similarVenueId) && $similarVenueId !== ''
                        ? Venue::query()->findOrFail($similarVenueId)
                        : Venue::create(Arr::only($data, VenueLocationSchema::venueAttributes()));

                    $team->addVenue($venue);

                    return (string) $venue->getKey();
                }),
            ...VenueLocationSchema::components(
                visible: fn (Get $get): bool => $get('venue_id') === BookingForm::OTHER_VENUE,
            ),
            ToggleButtons::make('priority')
                ->options(TaskPriority::class)
                ->default(TaskPriority::Medium->value)
                ->inline()
                ->grouped()
                ->required(),
            DateTimePicker::make('due_at')
                ->label(__('Due'))
                ->seconds(false),
            Select::make('assignee_id')
                ->label(__('Assignee'))
                ->options(fn (): array => static::assigneeOptions())
                ->searchable()
                ->nullable()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set, mixed $state, ?Task $record, ?LivewireComponent $livewire): void {
                    if (static::assigneeChanged($state, $record, $livewire)) {
                        $set('invite', true);
                    }
                })
                ->helperText(__('Optional. One person responsible for this task.')),
            static::inviteToggle(),
        ];
    }

    public static function inviteToggle(): ToggleButtons
    {
        return ToggleButtons::make('invite')
            ->hiddenLabel()
            ->boolean(__('Invite'), __('Accepted'))
            ->colors([
                1 => Palette::uncertain(),
                0 => Palette::positive(),
            ])
            ->icons([
                1 => Heroicon::OutlinedEnvelope,
                0 => Heroicon::OutlinedCheckCircle,
            ])
            ->grouped()
            ->default(true)
            ->visible(fn (Get $get, ?Task $record = null, ?LivewireComponent $livewire = null): bool => static::assigneeChanged(
                $get('assignee_id'),
                $record,
                $livewire,
            ))
            ->dehydrated(fn (Get $get, ?Task $record = null, ?LivewireComponent $livewire = null): bool => static::assigneeChanged(
                $get('assignee_id'),
                $record,
                $livewire,
            ));
    }

    /**
     * Invite / Accepted only applies when choosing a new assignee (create, first assign, or reassign).
     */
    public static function assigneeChanged(mixed $assigneeId, ?Task $record = null, ?LivewireComponent $livewire = null): bool
    {
        if (blank($assigneeId)) {
            return false;
        }

        $task = $record instanceof Task
            ? $record
            : (is_object($livewire) && property_exists($livewire, 'record') && $livewire->record instanceof Task
                ? $livewire->record
                : null);

        $currentId = $task?->attendance?->user_id;

        if ($currentId === null) {
            return true;
        }

        return (string) $assigneeId !== (string) $currentId;
    }

    /**
     * @return array<string, string>
     */
    public static function assigneeOptions(): array
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team) {
            return [];
        }

        return $team->members()
            ->where('suspended', false)
            ->whereNotNull('email_verified_at')
            ->ordered()
            ->pluck('users.name', 'users.id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateTaskFormData(array $data, Task $task): array
    {
        $task->loadMissing('attendance');
        $data['assignee_id'] = $task->attendance?->user_id;
        $data['invite'] = $task->attendance?->status === TaskAttendeeStatus::NeedsAction;

        $privateVenue = Venue::onlyPrivate()
            ->where('task_id', $task->getKey())
            ->whereKey($task->venue_id)
            ->first();

        if ($privateVenue instanceof Venue) {
            $data['venue_id'] = BookingForm::OTHER_VENUE;

            foreach (VenueLocationSchema::venueAttributes() as $attribute) {
                $data[$attribute] = $privateVenue->{$attribute};
            }

            $data['has_map_pin'] = $privateVenue->coords !== null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeVenueData(array $data): array
    {
        $isPrivateVenue = ($data['venue_id'] ?? null) === BookingForm::OTHER_VENUE;
        $data['private_venue'] = $isPrivateVenue
            ? Arr::only($data, VenueLocationSchema::venueAttributes())
            : null;

        if ($isPrivateVenue) {
            $data['venue_id'] = null;
        }

        foreach (VenueLocationSchema::venueAttributes() as $attribute) {
            unset($data[$attribute]);
        }

        unset(
            $data['similar_venue_id'],
            $data['has_map_pin'],
            $data['map'],
            $data['status'],
            $data['started_at'],
            $data['completed_at'],
            $data['assignee_id'],
            $data['invite'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: ?string, 2: bool}
     */
    public static function extractAssigneeId(array $data): array
    {
        $assigneeId = $data['assignee_id'] ?? null;
        $invite = (bool) ($data['invite'] ?? true);
        unset($data['assignee_id'], $data['invite']);

        return [
            $data,
            is_string($assigneeId) && $assigneeId !== '' ? $assigneeId : null,
            $invite,
        ];
    }

    private static function defaultVenueId(): ?string
    {
        $team = Filament::getTenant();
        $defaultId = $team instanceof Team ? $team->default_venue_id : null;

        return $defaultId === null ? null : (string) $defaultId;
    }
}
