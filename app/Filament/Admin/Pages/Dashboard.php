<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\ActivitiesWithoutTeamsWidget;
use App\Filament\Admin\Widgets\AdminAllCaughtUpWidget;
use App\Filament\Admin\Widgets\AdminSnapshotWidget;
use App\Filament\Admin\Widgets\AudiencesWithoutActivitiesWidget;
use App\Filament\Admin\Widgets\InboundNeedsReviewWidget;
use App\Filament\Admin\Widgets\KeysWithoutLocksWidget;
use App\Filament\Admin\Widgets\PendingSiteInvitesWidget;
use App\Filament\Admin\Widgets\TeamsWithoutManagersWidget;
use App\Filament\Admin\Widgets\VenuesWithoutTeamsWidget;
use App\Filament\Pages\Dashboard as BaseDashboard;
use App\Models\User;
use App\Support\AdminAttention;
use BackedEnum;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Admin';

    protected static ?int $navigationSort = -2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::AdjustmentsHorizontal;

    protected static string $panel = 'admin';

    /**
     * @return list<class-string<Widget>>
     */
    protected function fullWidthWidgets(): array
    {
        return [
            AdminSnapshotWidget::class,
            AdminAllCaughtUpWidget::class,
        ];
    }

    /**
     * Priority order for triage packing (first items prefer the shorter column).
     *
     * @return list<class-string<Widget>>
     */
    protected function triageWidgetOrder(): array
    {
        return [
            InboundNeedsReviewWidget::class,
            PendingSiteInvitesWidget::class,
            TeamsWithoutManagersWidget::class,
            ActivitiesWithoutTeamsWidget::class,
            VenuesWithoutTeamsWidget::class,
            AudiencesWithoutActivitiesWidget::class,
            KeysWithoutLocksWidget::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User || ! static::canAccess()) {
            return null;
        }

        $total = AdminAttention::for($user)->summary()['total'];

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $user = auth()->user();

        if (! $user instanceof User || ! static::canAccess()) {
            return null;
        }

        return AdminAttention::for($user)->summary()['severity']?->filamentColor();
    }

    /**
     * @return array<class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            ...$this->fullWidthWidgets(),
            ...$this->triageWidgetOrder(),
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make(1)
            ->schema(fn (): array => [
                ...$this->getWidgetsSchemaComponents($this->fullWidthWidgets()),
                ...$this->triageColumnsSchema(),
            ]);
    }

    /**
     * @return list<Component>
     */
    protected function triageColumnsSchema(): array
    {
        [$left, $right] = $this->packTriageColumns();

        if ($left === [] && $right === []) {
            return [];
        }

        $columns = ($left !== [] && $right !== [])
            ? ['default' => 1, 'md' => 2]
            : 1;

        $groups = [];

        if ($left !== []) {
            $groups[] = Group::make()
                ->extraAttributes(['class' => 'space-y-6'])
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($left));
        }

        if ($right !== []) {
            $groups[] = Group::make()
                ->extraAttributes(['class' => 'space-y-6'])
                ->schema(fn (): array => $this->getWidgetsSchemaComponents($right));
        }

        return [
            Grid::make($columns)->schema($groups),
        ];
    }

    /**
     * Pack non-empty triage widgets into two columns, assigning each to the
     * currently shorter column (by preview weight) so empty fixed sides collapse.
     *
     * @return array{0: list<class-string<Widget>>, 1: list<class-string<Widget>>}
     */
    protected function packTriageColumns(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [[], []];
        }

        $attention = AdminAttention::for($user);
        $weights = [
            InboundNeedsReviewWidget::class => $attention->inboundNeedingReview()->count(),
            PendingSiteInvitesWidget::class => $attention->pendingSiteInvites()->count(),
            TeamsWithoutManagersWidget::class => $attention->teamsWithoutManagers()->count(),
            ActivitiesWithoutTeamsWidget::class => $attention->activitiesWithoutTeams()->count(),
            VenuesWithoutTeamsWidget::class => $attention->venuesWithoutTeams()->count(),
            AudiencesWithoutActivitiesWidget::class => $attention->audiencesWithoutActivities()->count(),
            KeysWithoutLocksWidget::class => $attention->keysWithoutLocks()->count(),
        ];

        $left = [];
        $right = [];
        $leftWeight = 0;
        $rightWeight = 0;

        foreach ($this->triageWidgetOrder() as $widget) {
            $weight = $weights[$widget] ?? 0;

            if ($weight < 1 || ! $widget::canView()) {
                continue;
            }

            $visualWeight = min($weight, AdminAttention::PREVIEW_LIMIT);

            if ($leftWeight <= $rightWeight) {
                $left[] = $widget;
                $leftWeight += $visualWeight;
            } else {
                $right[] = $widget;
                $rightWeight += $visualWeight;
            }
        }

        return [$left, $right];
    }
}
