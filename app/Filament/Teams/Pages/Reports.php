<?php

namespace App\Filament\Teams\Pages;

use App\Enums\BookingStatus;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Support\ManagerBookingReports;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Reports';

    protected static ?string $slug = 'reports';

    protected static ?int $navigationSort = -3;

    protected string $view = 'filament.teams.pages.reports';

    #[Url]
    public int $year = 0;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User
            && $team instanceof Team
            && $user->manages($team);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Reports');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $years = $this->availableYears();

        if ($this->year === 0 || ! in_array($this->year, $years, true)) {
            $this->year = $years[0] ?? (int) now()->year;
        }
    }

    public function updatedYear(int $year): void
    {
        if (! in_array($year, $this->availableYears(), true)) {
            $this->year = $this->availableYears()[0] ?? (int) now()->year;
        }
    }

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        return $this->reports()->availableYears($this->team());
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->reports()->summary($this->team(), $this->year);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function activityBreakdown(): Collection
    {
        return $this->reports()->breakdownByActivity($this->team(), $this->year);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function audienceBreakdown(): Collection
    {
        return $this->reports()->breakdownByAudience($this->team(), $this->year);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function venueBreakdown(): Collection
    {
        return $this->reports()->breakdownByVenue($this->team(), $this->year);
    }

    /**
     * @return list<array{year: int, confirmed: int, cancelled: int}>
     */
    public function yearTrend(): array
    {
        return $this->reports()->yearTrend($this->team(), $this->year);
    }

    public function pastTentativeLiveCount(): int
    {
        return $this->reports()->pastTentativeLiveCount($this->team());
    }

    /**
     * @return Collection<int, Booking>
     */
    public function pastTentativeBookings(): Collection
    {
        return $this->reports()->pastTentativeBookings($this->team(), $this->year);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function memberEngagement(): Collection
    {
        return $this->reports()->memberEngagement($this->team(), $this->year);
    }

    public function historyBookingsUrl(?BookingStatus $status = null): string
    {
        $parameters = [
            'tenant' => $this->team(),
            'tab' => 'history',
        ];

        if ($status instanceof BookingStatus) {
            $parameters['filters'] = [
                'status' => ['value' => $status->value],
            ];
        }

        return BookingResource::getUrl('index', $parameters);
    }

    public function memberUrl(User $member): string
    {
        return MemberResource::getUrl('view', [
            'record' => $member,
            'tenant' => $this->team(),
        ]);
    }

    private function team(): Team
    {
        $team = Filament::getTenant();
        assert($team instanceof Team);

        return $team;
    }

    private function reports(): ManagerBookingReports
    {
        return app(ManagerBookingReports::class);
    }
}
