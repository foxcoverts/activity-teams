<?php

namespace App\Support;

use App\Filament\Teams\Resources\BookingDebriefs\BookingDebriefResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Documents\DocumentResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Models\Document;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

final class RecoverTeamScopedRecordUrl
{
    /**
     * @return array<string, array{model: class-string<Model>, resource: class-string, team: callable(Model): (?Team)}>
     */
    private static function registry(): array
    {
        return [
            'bookings' => [
                'model' => Booking::class,
                'resource' => BookingResource::class,
                'team' => fn (Booking $record): ?Team => $record->team,
            ],
            'tasks' => [
                'model' => Task::class,
                'resource' => TaskResource::class,
                'team' => fn (Task $record): ?Team => $record->team,
            ],
            'news' => [
                'model' => NewsPost::class,
                'resource' => NewsPostResource::class,
                'team' => fn (NewsPost $record): ?Team => $record->team,
            ],
            'debriefs' => [
                'model' => BookingDebrief::class,
                'resource' => BookingDebriefResource::class,
                'team' => function (BookingDebrief $record): ?Team {
                    $record->loadMissing('booking.team');

                    return $record->booking?->team;
                },
            ],
            'documents' => [
                'model' => Document::class,
                'resource' => DocumentResource::class,
                'team' => fn (Document $record): ?Team => $record->team,
            ],
        ];
    }

    public function url(Request $request): ?string
    {
        $route = $request->route();

        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        if (! is_string($name) || ! preg_match('/^filament\.teams\.resources\.([a-z0-9-]+)\.(view|edit)$/', $name, $matches)) {
            return null;
        }

        $entry = self::registry()[$matches[1]] ?? null;

        if ($entry === null) {
            return null;
        }

        $tenantParameter = $route->parameter('tenant');
        $recordParameter = $route->parameter('record');

        if ($tenantParameter === null || $recordParameter === null) {
            return null;
        }

        $urlSlug = $tenantParameter instanceof Team
            ? (string) $tenantParameter->slug
            : (string) $tenantParameter;

        $recordKey = $recordParameter instanceof Model
            ? $recordParameter->getRouteKey()
            : $recordParameter;

        /** @var Model $model */
        $model = new $entry['model'];
        $query = $entry['model']::query();
        $tenancyScope = Filament::getCurrentPanel()?->getTenancyScopeName();

        if (is_string($tenancyScope)) {
            $query->withoutGlobalScope($tenancyScope);
        }

        try {
            $record = $query->where($model->getRouteKeyName(), $recordKey)->first();
        } catch (Throwable) {
            return null;
        }

        if (! $record instanceof Model) {
            return null;
        }

        $record->loadMissing(match ($matches[1]) {
            'debriefs' => ['booking.team'],
            default => ['team'],
        });

        /** @var ?Team $team */
        $team = ($entry['team'])($record);

        if (! $team instanceof Team || (string) $team->slug === $urlSlug) {
            return null;
        }

        $user = $request->user();

        if ($user instanceof User) {
            // Keep Filament tenant isolation: a real (but wrong) team slug stays 404.
            // Only rewrite unknown/broken slugs, and only when the user can open that team.
            if (Team::query()->where('slug', $urlSlug)->exists()) {
                return null;
            }

            if (! $user->canAccessTenant($team)) {
                return null;
            }
        } elseif ($matches[1] !== 'bookings' || $matches[2] !== 'view') {
            return null;
        }

        return $entry['resource']::getUrl(
            $matches[2],
            ['record' => $record],
            panel: 'teams',
            tenant: $team,
        );
    }
}
