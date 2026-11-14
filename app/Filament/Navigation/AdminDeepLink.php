<?php

namespace App\Filament\Navigation;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Admin\Resources\Activities\ActivityResource as AdminActivityResource;
use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Teams\TeamResource as AdminTeamResource;
use App\Filament\Admin\Resources\Users\UserResource as AdminUserResource;
use App\Filament\Admin\Resources\Venues\VenueResource as AdminVenueResource;
use App\Filament\Teams\Resources\Activities\ActivityResource as TeamsActivityResource;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamsKeyResource;
use App\Filament\Teams\Resources\Members\MemberResource as TeamsMemberResource;
use App\Filament\Teams\Resources\Venues\VenueResource as TeamsVenueResource;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page as FilamentPage;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Livewire\Livewire;

use function Filament\Support\original_request;

class AdminDeepLink
{
    /**
     * Source resource → Admin destination resource.
     *
     * @var array<class-string, class-string<resource>>
     */
    private const DESTINATIONS = [
        TeamsActivityResource::class => AdminActivityResource::class,
        AdminActivityResource::class => AdminActivityResource::class,
        TeamsVenueResource::class => AdminVenueResource::class,
        AdminVenueResource::class => AdminVenueResource::class,
        TeamsKeyResource::class => AdminKeyResource::class,
        AdminKeyResource::class => AdminKeyResource::class,
        TeamsMemberResource::class => AdminUserResource::class,
        AdminUserResource::class => AdminUserResource::class,
        AdminTeamResource::class => AdminTeamResource::class,
    ];

    /**
     * Route name prefixes → Admin destination.
     *
     * @var array<string, class-string<resource>>
     */
    private const ROUTE_DESTINATIONS = [
        'filament.teams.resources.activities.' => AdminActivityResource::class,
        'filament.admin.resources.activities.' => AdminActivityResource::class,
        'filament.teams.resources.venues.' => AdminVenueResource::class,
        'filament.admin.resources.venues.' => AdminVenueResource::class,
        'filament.teams.resources.keys.' => AdminKeyResource::class,
        'filament.admin.resources.keys.' => AdminKeyResource::class,
        'filament.teams.resources.members.' => AdminUserResource::class,
        'filament.admin.resources.users.' => AdminUserResource::class,
        'filament.admin.resources.teams.' => AdminTeamResource::class,
    ];

    public static function url(): string
    {
        $user = auth()->user();
        $panel = Filament::getPanel('admin');

        if (! $user instanceof User || ! $panel instanceof Panel || ! $user->canAccessPanel($panel)) {
            return '';
        }

        $fallback = AdminDashboard::getUrl(panel: 'admin');
        $destination = static::currentDestinationClass();

        if ($destination !== null && is_subclass_of($destination, Resource::class)) {
            if ($destination::canViewAny()) {
                return $destination::getUrl(name: 'index', panel: 'admin');
            }

            return $fallback;
        }

        $team = static::currentTeamContext();

        if ($team instanceof Team && AdminTeamResource::canEdit($team)) {
            return AdminTeamResource::getUrl(
                name: 'edit',
                parameters: ['record' => $team],
                panel: 'admin',
            );
        }

        return $fallback;
    }

    protected static function currentTeamContext(): ?Team
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Team) {
            return $tenant;
        }

        $route = original_request()->route() ?? request()->route();
        $parameter = $route?->parameter('tenant') ?? $route?->parameter('record');

        return $parameter instanceof Team ? $parameter : null;
    }

    /**
     * @return class-string<resource>|null
     */
    protected static function currentDestinationClass(): ?string
    {
        $component = Livewire::current();

        if ($component instanceof ResourcePage) {
            $resource = $component::getResource();

            return self::DESTINATIONS[$resource] ?? null;
        }

        if ($component instanceof FilamentPage) {
            return self::DESTINATIONS[$component::class] ?? null;
        }

        $routeName = original_request()->route()?->getName()
            ?? request()->route()?->getName()
            ?? '';

        if ($routeName === '') {
            return null;
        }

        foreach (self::ROUTE_DESTINATIONS as $prefix => $destination) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix)) {
                return $destination;
            }
        }

        return null;
    }
}
