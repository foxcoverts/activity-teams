<?php

namespace App\Filament\Member\Widgets;

use App\Models\User;
use App\Support\MemberDashboard;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;
use Throwable;

/**
 * A member dashboard section. It loads its own data, reports and isolates its own failure, and refreshes when attendance or inbox state changes elsewhere on the page.
 */
abstract class DashboardSection extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    abstract protected function sectionData(): array;

    #[On('booking-attendance-updated')]
    #[On('dashboard-updated')]
    public function refreshSection(): void {}

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

    protected function dashboard(): MemberDashboard
    {
        $user = auth()->user();
        assert($user instanceof User);

        return app(MemberDashboard::class, ['user' => $user]);
    }
}
