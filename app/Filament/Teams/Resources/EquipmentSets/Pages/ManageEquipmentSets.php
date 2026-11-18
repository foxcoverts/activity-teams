<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Pages;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Filament\Teams\Resources\EquipmentSets\Schemas\EquipmentSetForm;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManageEquipmentSets extends Page
{
    protected static string $resource = EquipmentSetResource::class;

    protected static ?string $title = 'Equipment sets';

    protected string $view = 'filament.equipment-sets.manage';

    public function mount(): void
    {
        abort_unless(EquipmentSetResource::canViewAny(), 403);

        $team = $this->team();
        EquipmentSetGroup::ensureDefaultFor($team);
    }

    /**
     * @return Collection<int, EquipmentSetGroup>
     */
    public function groups(): Collection
    {
        return $this->team()
            ->equipmentSetGroups()
            ->with(['equipmentSets' => fn ($query) => $query->with('activities')->orderBy('name')])
            ->orderBy('rank')
            ->get();
    }

    public function moveSetToGroup(string $setId, string $groupId): void
    {
        $set = $this->set($setId);
        $group = $this->group($groupId);

        Gate::authorize('update', $set);
        Gate::authorize('update', $group);

        if ((string) $set->equipment_set_group_id === (string) $group->getKey()) {
            return;
        }

        $set->forceFill([
            'equipment_set_group_id' => $group->getKey(),
        ])->save();

        Notification::make()
            ->success()
            ->title(__('Moved to :group', ['group' => $group->label]))
            ->send();
    }

    /**
     * @param  list<string>  $orderedIds
     */
    public function reorderGroups(array $orderedIds): void
    {
        $team = $this->team();
        Gate::authorize('create', EquipmentSetGroup::class);

        EquipmentSetGroup::reorderForTeam($team, $orderedIds);
    }

    public function moveGroupUp(string $groupId): void
    {
        $group = $this->group($groupId);
        Gate::authorize('update', $group);
        $group->moveUp();
    }

    public function moveGroupDown(string $groupId): void
    {
        $group = $this->group($groupId);
        Gate::authorize('update', $group);
        $group->moveDown();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('createGroup')
                ->label(__('New group'))
                ->model(EquipmentSetGroup::class)
                ->authorize(fn (): bool => Gate::allows('create', EquipmentSetGroup::class))
                ->schema([
                    Section::make(__('Group'))
                        ->icon(Heroicon::OutlinedRectangleStack)
                        ->columns(1)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('label')
                                ->label(__('Label'))
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    modifyRuleUsing: function ($rule) {
                                        $team = $this->team();

                                        return $rule->where('team_id', $team->getKey());
                                    },
                                ),
                        ]),
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $team = $this->team();
                    EquipmentSetGroup::ensureDefaultFor($team);
                    $data['team_id'] = $team->getKey();
                    $data['rank'] = EquipmentSetGroup::nextRankFor($team);

                    return $data;
                })
                ->successNotificationTitle(__('Group created')),
        ];
    }

    public function editGroupAction(): Action
    {
        return Action::make('editGroup')
            ->label(__('Edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                Section::make(__('Group'))
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('label')
                            ->label(__('Label'))
                            ->required()
                            ->maxLength(255),
                    ]),
            ])
            ->fillForm(fn (array $arguments): array => [
                'label' => $this->group((string) ($arguments['group'] ?? ''))->label,
            ])
            ->action(function (array $arguments, array $data): void {
                $group = $this->group((string) ($arguments['group'] ?? ''));
                Gate::authorize('update', $group);
                $group->update(['label' => $data['label']]);

                Notification::make()->success()->title(__('Group updated'))->send();
            });
    }

    public function deleteGroupAction(): Action
    {
        return Action::make('deleteGroup')
            ->label(__('Remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color(Palette::destructive())
            ->requiresConfirmation()
            ->modalHeading(__('Remove group?'))
            ->modalDescription(__('Sets in this group will move to another group.'))
            ->action(function (array $arguments): void {
                $group = $this->group((string) ($arguments['group'] ?? ''));
                Gate::authorize('delete', $group);

                try {
                    $group->deleteAndReassign();
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title(collect($exception->errors())->flatten()->first() ?: __('Unable to delete group.'))
                        ->send();

                    return;
                }

                Notification::make()->success()->title(__('Group removed'))->send();
            });
    }

    public function addSetAction(): Action
    {
        return Action::make('addSet')
            ->label(__('Add set'))
            ->icon(Heroicon::OutlinedPlus)
            ->button()
            ->color('gray')
            ->modalHeading(__('New equipment set'))
            ->modalSubmitActionLabel(__('Create'))
            ->schema(fn (array $arguments): array => [
                Section::make(__('Equipment set'))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(EquipmentSetForm::fields(
                        lockedGroupId: (string) ($arguments['group'] ?? ''),
                    )),
            ])
            ->action(function (array $arguments, array $data): void {
                $group = $this->group((string) ($arguments['group'] ?? ''));
                Gate::authorize('create', EquipmentSet::class);

                $set = EquipmentSet::query()->create([
                    'team_id' => $this->team()->getKey(),
                    'equipment_set_group_id' => $group->getKey(),
                    'name' => $data['name'],
                ]);
                $set->activities()->sync($data['activities'] ?? []);

                Notification::make()->success()->title(__('Equipment set created'))->send();
            });
    }

    public function editSetAction(): Action
    {
        return Action::make('editSet')
            ->label(__('Edit'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('Edit equipment set'))
            ->schema(fn (array $arguments): array => [
                Section::make(__('Equipment set'))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(EquipmentSetForm::fields(
                        record: $this->set((string) ($arguments['set'] ?? '')),
                    )),
            ])
            ->fillForm(function (array $arguments): array {
                $set = $this->set((string) ($arguments['set'] ?? ''));

                return [
                    'name' => $set->name,
                    'equipment_set_group_id' => $set->equipment_set_group_id,
                    'activities' => $set->activities->modelKeys(),
                ];
            })
            ->action(function (array $arguments, array $data): void {
                $set = $this->set((string) ($arguments['set'] ?? ''));
                Gate::authorize('update', $set);

                $group = $this->group((string) $data['equipment_set_group_id']);
                Gate::authorize('update', $group);

                $set->update([
                    'name' => $data['name'],
                    'equipment_set_group_id' => $group->getKey(),
                ]);
                $set->activities()->sync($data['activities'] ?? []);

                Notification::make()->success()->title(__('Equipment set updated'))->send();
            });
    }

    public function moveSetAction(): Action
    {
        return Action::make('moveSet')
            ->label(__('Move to…'))
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->modalHeading(__('Move equipment set'))
            ->modalSubmitActionLabel(__('Move'))
            ->schema(fn (array $arguments): array => [
                Select::make('equipment_set_group_id')
                    ->label(__('Group'))
                    ->required()
                    ->options(function () use ($arguments): array {
                        $set = $this->set((string) ($arguments['set'] ?? ''));

                        return $this->team()
                            ->equipmentSetGroups()
                            ->whereKeyNot($set->equipment_set_group_id)
                            ->orderBy('rank')
                            ->pluck('label', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->moveSetToGroup(
                    (string) ($arguments['set'] ?? ''),
                    (string) $data['equipment_set_group_id'],
                );
            });
    }

    public function deleteSetAction(): Action
    {
        return Action::make('deleteSet')
            ->label(__('Remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color(Palette::destructive())
            ->requiresConfirmation()
            ->modalHeading(__('Remove equipment set?'))
            ->action(function (array $arguments): void {
                $set = $this->set((string) ($arguments['set'] ?? ''));
                Gate::authorize('delete', $set);
                $set->delete();

                Notification::make()->success()->title(__('Removed'))->send();
            });
    }

    private function team(): Team
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return $team;
    }

    private function group(string $groupId): EquipmentSetGroup
    {
        $group = $this->team()->equipmentSetGroups()->whereKey($groupId)->first();
        abort_unless($group instanceof EquipmentSetGroup, 404);

        return $group;
    }

    private function set(string $setId): EquipmentSet
    {
        $set = $this->team()->equipmentSets()->whereKey($setId)->first();
        abort_unless($set instanceof EquipmentSet, 404);

        return $set;
    }

    /**
     * @return array{canDelete: bool, canMoveUp: bool, canMoveDown: bool}
     */
    public function groupCapabilities(EquipmentSetGroup $group): array
    {
        return [
            'canDelete' => Gate::allows('delete', $group),
            'canMoveUp' => $group->canMoveUp(),
            'canMoveDown' => $group->canMoveDown(),
        ];
    }

    public function activityBadges(EquipmentSet $set): array
    {
        $names = $set->activities->pluck('name')->filter()->values()->all();

        if ($names === []) {
            return [
                ['label' => __('None'), 'color' => Palette::destructive()],
            ];
        }

        return array_map(
            fn (string $name): array => ['label' => $name, 'color' => 'primary'],
            $names,
        );
    }
}
