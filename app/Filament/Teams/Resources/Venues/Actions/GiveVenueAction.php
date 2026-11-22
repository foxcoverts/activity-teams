<?php

namespace App\Filament\Teams\Resources\Venues\Actions;

use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\Resources\Venues\VenueResource;
use App\Models\Team;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class GiveVenueAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'give';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Give a venue')
            ->modalHeading(__('Give a venue'))
            ->modalIcon(Heroicon::OutlinedMapPin)
            ->authorize(fn (): bool => VenueResource::canViewAny() && auth()->user()?->isSystemAdmin() === true)
            ->fillForm(fn (): array => [
                'set_as_default' => VenueResource::currentTeam()?->default_venue_id === null,
            ])
            ->schema([
                Select::make('venue_id')
                    ->label('Venue')
                    ->options(function (): array {
                        $team = VenueResource::currentTeam();

                        if ($team === null) {
                            return [];
                        }

                        return Venue::selectOptions(
                            Venue::query()->whereDoesntHave(
                                'teams',
                                fn ($teams) => $teams->whereKey($team->getKey()),
                            ),
                        );
                    })
                    ->searchable()
                    ->required(),
                Toggle::make('set_as_default')
                    ->label(__('Set as default'))
                    ->disabled(fn (): bool => VenueResource::currentTeam()?->default_venue_id === null)
                    ->dehydrated(),
            ])
            ->action(function (array $data, Action $action): void {
                $team = VenueResource::currentTeam();
                abort_unless($team instanceof Team, 404);

                $hadDefault = $team->default_venue_id !== null;

                $venue = Venue::query()
                    ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                    ->findOrFail($data['venue_id']);

                $team->addVenue($venue);

                if ((bool) ($data['set_as_default'] ?? false) && $hadDefault) {
                    $team->setDefaultVenue($venue);
                }

                RefreshesNavigationChrome::dispatch($action->getLivewire());
            });
    }
}
