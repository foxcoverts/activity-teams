<?php

namespace App\Filament\Teams\Pages;

use App\Filament\Teams\NavigationGroup;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class Leaders extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Star;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $title = 'Team Leaders';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Leaders';

    protected string $view = 'filament.teams.pages.leaders';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User && $team instanceof Team && $user->isCurrentMemberOf($team);
    }

    /**
     * @return Collection<int, User>
     */
    public function leaders(): Collection
    {
        return once(fn (): Collection => $this->team()->activeManagers()->ordered()->get());
    }

    public function contactAction(): Action
    {
        return Action::make('contact')
            ->label(__('Contact'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => $this->leader($arguments)->name)
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalContent(fn (array $arguments): View => view('filament.teams.leaders-host', [
                'team' => $this->team(),
                'person' => $this->leader($arguments),
            ]));
    }

    private function team(): Team
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return $team;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function leader(array $arguments): User
    {
        $leader = $this->leaders()->firstWhere('id', $arguments['leader'] ?? null);

        abort_unless($leader instanceof User, 404);

        return $leader;
    }
}
