<?php

namespace App\Filament\Teams\Resources\Activities\Actions;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\Activities\ActivityResource;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Team;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;

class GiveActivityAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'give';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => auth()->user()?->isSystemAdmin() ? 'Give an activity' : 'Add an activity')
            ->modalHeading(fn (): string => auth()->user()?->isSystemAdmin() ? __('Give an activity') : __('Add an activity'))
            ->modalIcon(Heroicon::OutlinedFlag)
            ->authorize(fn (): bool => ActivityResource::canViewAny())
            ->fillForm(fn (): array => [
                'set_as_default' => ActivityResource::currentTeam()?->default_activity_id === null,
            ])
            ->schema([
                Select::make('activity_id')
                    ->label('Activity')
                    ->options(function (): array {
                        $team = ActivityResource::currentTeam();

                        if ($team === null) {
                            return [];
                        }

                        return Activity::query()
                            ->withAudiences()
                            ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->live()
                    ->required(),
                Select::make('default_audience_id')
                    ->label(__('Default audience'))
                    ->options(fn (Get $get): array => static::audienceOptions($get('activity_id')))
                    ->placeholder(__('None'))
                    ->visible(fn (Get $get): bool => filled($get('activity_id')))
                    ->disabled(fn (Get $get): bool => static::audienceOptions($get('activity_id')) === []),
                Toggle::make('set_as_default')
                    ->label(__('Set as default'))
                    ->disabled(fn (): bool => ActivityResource::currentTeam()?->default_activity_id === null)
                    ->dehydrated(),
            ])
            ->action(function (array $data, Action $action): void {
                $team = ActivityResource::currentTeam();
                abort_unless($team instanceof Team, 404);

                $hadDefault = $team->default_activity_id !== null;

                $activity = Activity::query()
                    ->withAudiences()
                    ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                    ->findOrFail($data['activity_id']);

                $team->addActivity($activity);

                if ((bool) ($data['set_as_default'] ?? false) && $hadDefault) {
                    $team->setDefaultActivity($activity);
                }

                $audienceId = $data['default_audience_id'] ?? null;

                if (filled($audienceId)) {
                    $audience = Audience::query()->find($audienceId);
                    $team->setDefaultAudience($activity, $audience instanceof Audience ? $audience : null);
                }

                RefreshesNavigationChrome::dispatch($action->getLivewire());
            });
    }

    /**
     * @return array<string, string>
     */
    private static function audienceOptions(mixed $activityId): array
    {
        if (blank($activityId)) {
            return [];
        }

        $activity = Activity::query()
            ->with('activityAudiences.audience')
            ->find($activityId);

        if (! $activity instanceof Activity) {
            return [];
        }

        return $activity->activityAudiences
            ->sortBy(fn ($pairing) => $pairing->audience?->name)
            ->mapWithKeys(fn ($pairing): array => [
                (string) $pairing->audience_id => (string) $pairing->audience?->name,
            ])
            ->all();
    }
}
