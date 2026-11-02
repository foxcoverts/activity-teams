<?php

namespace App\Filament\Manage\Resources\Venues\Actions;

use App\Filament\Manage\Resources\Venues\VenueResource;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

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
            ->authorize(fn (): bool => VenueResource::canViewAny() && auth()->user()?->isSystemAdmin() === true)
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
            ])
            ->action(function (array $data): void {
                $team = VenueResource::currentTeam();
                abort_unless($team !== null, 404);

                $venue = Venue::query()
                    ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                    ->findOrFail($data['venue_id']);

                $team->addVenue($venue);
            });
    }
}
