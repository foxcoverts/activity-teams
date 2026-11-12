<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Teams\Pages\ManagerDashboard;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Throwable;

class AdminTeamVisitorWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.teams.widgets.admin-team-visitor';

    public static function canView(): bool
    {
        return static::viewerIsNonMemberAdmin();
    }

    public static function viewerIsNonMemberAdmin(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User
            && $team instanceof Team
            && $user->isSystemAdmin()
            && ! $user->isCurrentMemberOf($team);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            return ['failed' => false, ...$this->sectionData()];
        } catch (Throwable $e) {
            report($e);

            return ['failed' => true];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function sectionData(): array
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return [
            'heading' => __('Viewing as system admin'),
            'icon' => Heroicon::OutlinedShieldCheck,
            'iconColor' => Palette::manager(),
            'teamName' => $team->name,
            'managerUrl' => ManagerDashboard::canAccess()
                ? ManagerDashboard::getUrl(tenant: $team)
                : null,
            'membersUrl' => MemberResource::canViewAny()
                ? MemberResource::getUrl('index', tenant: $team)
                : null,
        ];
    }
}
