<?php

namespace App\Filament\Navigation;

use App\Filament\Admin\Resources\Activities\ActivityResource as AdminActivityResource;
use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Locks\LockResource as AdminLockResource;
use App\Filament\Admin\Resources\Teams\Pages\EditTeam as AdminEditTeam;
use App\Filament\Admin\Resources\Venues\VenueResource as AdminVenueResource;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Member\Resources\NewsPosts\NewsPostResource as MemberNewsPostResource;
use App\Filament\Member\Resources\Tasks\TaskResource as MemberTaskResource;
use App\Filament\Teams\Pages\Dashboard as TeamsDashboard;
use App\Filament\Teams\Pages\Leaders;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Filament\Teams\Resources\Activities\ActivityResource as TeamsActivityResource;
use App\Filament\Teams\Resources\BookingDebriefs\BookingDebriefResource;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Filament\Teams\Resources\Documents\DocumentResource;
use App\Filament\Teams\Resources\EquipmentSetGroups\EquipmentSetGroupResource;
use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamsKeyResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource as TeamsNewsPostResource;
use App\Filament\Teams\Resources\Tasks\TaskResource as TeamsTaskResource;
use App\Filament\Teams\Resources\Venues\VenueResource as TeamsVenueResource;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page as FilamentPage;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

use function Filament\Support\original_request;

class TeamsDeepLink
{
    /**
     * Source resource / page → Teams destination resource or page.
     *
     * @var array<class-string, class-string<resource>|class-string<FilamentPage>>
     */
    private const DESTINATIONS = [
        MemberBookingResource::class => TeamsBookingResource::class,
        MemberNewsPostResource::class => TeamsNewsPostResource::class,
        MemberTaskResource::class => TeamsTaskResource::class,
        AdminActivityResource::class => TeamsActivityResource::class,
        AdminVenueResource::class => TeamsVenueResource::class,
        AdminKeyResource::class => TeamsKeyResource::class,
        AdminLockResource::class => TeamsKeyResource::class,
        TeamsBookingResource::class => TeamsBookingResource::class,
        BookingDebriefResource::class => BookingDebriefResource::class,
        DocumentResource::class => DocumentResource::class,
        TeamsKeyResource::class => TeamsKeyResource::class,
        Leaders::class => Leaders::class,
        MemberResource::class => MemberResource::class,
        TeamsNewsPostResource::class => TeamsNewsPostResource::class,
        TeamsTaskResource::class => TeamsTaskResource::class,
        TeamsActivityResource::class => TeamsActivityResource::class,
        EquipmentSetResource::class => EquipmentSetResource::class,
        EquipmentSetGroupResource::class => EquipmentSetGroupResource::class,
        TeamsVenueResource::class => TeamsVenueResource::class,
        TeamsDashboard::class => TeamsDashboard::class,
        ManagerDashboard::class => ManagerDashboard::class,
    ];

    /**
     * Route name prefixes → Teams destination (Livewire::current() is often unset while the sidebar builds).
     *
     * @var array<string, class-string<resource>|class-string<FilamentPage>>
     */
    private const ROUTE_DESTINATIONS = [
        'filament.member.resources.bookings.' => TeamsBookingResource::class,
        'filament.member.resources.news.' => TeamsNewsPostResource::class,
        'filament.member.resources.tasks.' => TeamsTaskResource::class,
        'filament.admin.resources.activities.' => TeamsActivityResource::class,
        'filament.admin.resources.venues.' => TeamsVenueResource::class,
        'filament.admin.resources.keys.' => TeamsKeyResource::class,
        'filament.admin.resources.locks.' => TeamsKeyResource::class,
        'filament.teams.resources.bookings.' => TeamsBookingResource::class,
        'filament.teams.resources.debriefs.' => BookingDebriefResource::class,
        'filament.teams.resources.documents.' => DocumentResource::class,
        'filament.teams.resources.keys.' => TeamsKeyResource::class,
        'filament.teams.resources.members.' => MemberResource::class,
        'filament.teams.resources.news.' => TeamsNewsPostResource::class,
        'filament.teams.resources.tasks.' => TeamsTaskResource::class,
        'filament.teams.resources.activities.' => TeamsActivityResource::class,
        'filament.teams.resources.equipment-sets.' => EquipmentSetResource::class,
        'filament.teams.resources.equipment-set-groups.' => EquipmentSetGroupResource::class,
        'filament.teams.resources.venues.' => TeamsVenueResource::class,
        'filament.teams.pages.dashboard' => TeamsDashboard::class,
        'filament.teams.pages.manager-dashboard' => ManagerDashboard::class,
        'filament.teams.pages.leaders' => Leaders::class,
    ];

    public static function url(?Team $tenant = null): string
    {
        $user = auth()->user();
        $panel = Filament::getPanel('teams');

        if (! $user instanceof User || ! $panel instanceof Panel) {
            return '';
        }

        $tenant ??= static::resolveTenant($user, $panel);

        if (! $tenant instanceof Team) {
            return '';
        }

        return static::urlForTenant($tenant);
    }

    public static function urlForTenant(Team $tenant): string
    {
        $panel = Filament::getPanel('teams');
        $fallback = $panel->getUrl($tenant) ?? '';

        $destination = static::currentDestinationClass();

        if ($destination === null) {
            return $fallback;
        }

        $originalTenant = Filament::getTenant();
        Filament::setTenant($tenant, isQuiet: true);

        try {
            if (is_subclass_of($destination, Resource::class)) {
                if (! $destination::canViewAny()) {
                    return $fallback;
                }

                return $destination::getUrl(name: 'index', panel: 'teams', tenant: $tenant);
            }

            if (is_subclass_of($destination, FilamentPage::class)) {
                if (method_exists($destination, 'canAccess') && ! $destination::canAccess()) {
                    return $fallback;
                }

                return $destination::getUrl(panel: 'teams', tenant: $tenant);
            }
        } finally {
            Filament::setTenant($originalTenant, isQuiet: true);
        }

        return $fallback;
    }

    protected static function resolveTenant(User $user, Panel $panel): ?Team
    {
        $fromRecord = static::tenantFromCurrentRecord();

        if ($fromRecord instanceof Team && $user->canAccessTenant($fromRecord)) {
            return $fromRecord;
        }

        $default = $user->getDefaultTenant($panel);

        return $default instanceof Team ? $default : null;
    }

    protected static function tenantFromCurrentRecord(): ?Team
    {
        $component = Livewire::current();

        if ($component instanceof AdminEditTeam) {
            $record = $component->getRecord();

            return $record instanceof Team ? $record : null;
        }

        if ($component instanceof ResourcePage && method_exists($component, 'getRecord')) {
            try {
                $record = $component->getRecord();
            } catch (\Throwable) {
                return null;
            }

            if ($record instanceof Team) {
                return $record;
            }

            if ($record instanceof Model && method_exists($record, 'team')) {
                $record->loadMissing('team');
            }

            $team = $record instanceof Model ? ($record->team ?? null) : null;

            return $team instanceof Team ? $team : null;
        }

        $route = original_request()->route() ?? request()->route();
        $team = $route?->parameter('record');

        return $team instanceof Team ? $team : null;
    }

    /**
     * @return class-string<resource>|class-string<FilamentPage>|null
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
