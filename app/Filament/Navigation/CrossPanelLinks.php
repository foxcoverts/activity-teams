<?php

namespace App\Filament\Navigation;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Member\Pages\Dashboard as MemberDashboard;
use App\Filament\Member\Pages\Logbook;
use App\Filament\Member\Pages\ReportIncident;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Member\Resources\Keys\KeyResource as MemberKeyResource;
use App\Filament\Member\Resources\Qualifications\QualificationResource;
use App\Filament\Teams\NavigationGroup as TeamsNavigationGroup;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;

class CrossPanelLinks
{
    /**
     * @return array<NavigationItem>
     */
    public static function forMemberPanel(): array
    {
        return [
            static::teams(sort: 98),
            static::admin(sort: 99),
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function forTeamsPanel(): array
    {
        return [
            static::home(sort: -3),
            static::inbox(),
            static::logbook(),
            static::myKeys(),
            static::qualifications(),
            static::reportIncident(),
            static::admin(
                group: TeamsNavigationGroup::Admin,
                sort: 1,
            ),
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function forAdminPanel(): array
    {
        return [
            static::backToTeams(sort: 98),
        ];
    }

    public static function home(?TeamsNavigationGroup $group = null, int $sort = -3): NavigationItem
    {
        return NavigationItem::make(__('Home'))
            ->key('cross-panel-home')
            ->group($group)
            ->icon(Heroicon::OutlinedHome)
            ->activeIcon(Heroicon::Home)
            ->sort($sort)
            ->url(fn (): string => MemberDashboard::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel());
    }

    public static function teams(int $sort = 98): NavigationItem
    {
        return NavigationItem::make(__('Teams'))
            ->key('cross-panel-teams')
            ->icon(Heroicon::OutlinedUserGroup)
            ->activeIcon(Heroicon::UserGroup)
            ->sort($sort)
            ->url(fn (): string => TeamsDeepLink::url())
            ->visible(fn (): bool => static::canAccessTeamsPanel());
    }

    public static function backToTeams(int $sort = 98): NavigationItem
    {
        return NavigationItem::make(__('Back to teams'))
            ->key('cross-panel-back-to-teams')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->activeIcon(Heroicon::ArrowUturnLeft)
            ->sort($sort)
            ->url(fn (): string => TeamsDeepLink::url())
            ->visible(fn (): bool => static::canAccessTeamsPanel());
    }

    protected static function canAccessTeamsPanel(): bool
    {
        $user = auth()->user();
        $panel = Filament::getPanel('teams');

        return $user instanceof User
            && $user->canAccessPanel($panel)
            && $user->getDefaultTenant($panel) instanceof Team;
    }

    public static function admin(?TeamsNavigationGroup $group = null, int $sort = 99): NavigationItem
    {
        return NavigationItem::make(__('Admin'))
            ->key('cross-panel-admin')
            ->group($group)
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->activeIcon(Heroicon::AdjustmentsHorizontal)
            ->sort($sort)
            ->badge(
                fn (): ?string => AdminDashboard::getNavigationBadge(),
                color: fn (): string|array|null => AdminDashboard::getNavigationBadgeColor(),
            )
            ->url(fn (): string => AdminDeepLink::url())
            ->visible(function (): bool {
                $user = auth()->user();
                $panel = Filament::getPanel('admin');

                return $user instanceof User && $user->canAccessPanel($panel);
            });
    }

    public static function inbox(): NavigationItem
    {
        return NavigationItem::make(__('Inbox'))
            ->key('cross-panel-inbox')
            ->group(TeamsNavigationGroup::Personal)
            ->icon(Heroicon::OutlinedInbox)
            ->sort(2)
            ->badge(fn (): ?string => InboxResource::getNavigationBadge())
            ->url(fn (): string => InboxResource::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel());
    }

    public static function logbook(): NavigationItem
    {
        return NavigationItem::make(__('Logbook'))
            ->key('cross-panel-logbook')
            ->group(TeamsNavigationGroup::Personal)
            ->icon(Heroicon::OutlinedBookOpen)
            ->sort(3)
            ->url(fn (): string => Logbook::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel());
    }

    public static function myKeys(): NavigationItem
    {
        return NavigationItem::make(__('My keys'))
            ->key('cross-panel-my-keys')
            ->group(TeamsNavigationGroup::Personal)
            ->icon(Heroicon::OutlinedKey)
            ->sort(4)
            ->url(fn (): string => MemberKeyResource::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel() && MemberKeyResource::canViewAny());
    }

    public static function qualifications(): NavigationItem
    {
        return NavigationItem::make(__('Qualifications'))
            ->key('cross-panel-qualifications')
            ->group(TeamsNavigationGroup::Personal)
            ->icon(Heroicon::OutlinedAcademicCap)
            ->sort(5)
            ->url(fn (): string => QualificationResource::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel() && QualificationResource::canViewAny());
    }

    public static function reportIncident(): NavigationItem
    {
        return NavigationItem::make(__('Report an incident'))
            ->key('cross-panel-report-incident')
            ->group(TeamsNavigationGroup::Personal)
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->sort(6)
            ->url(fn (): string => ReportIncident::getUrl(panel: 'member'))
            ->visible(fn (): bool => static::canAccessMemberPanel() && ReportIncident::canAccess());
    }

    protected static function canAccessMemberPanel(): bool
    {
        $user = auth()->user();
        $panel = Filament::getPanel('member');

        return $user instanceof User && $user->canAccessPanel($panel);
    }
}
