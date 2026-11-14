<?php

namespace App\Filament\Member\Widgets;

use App\Models\Booking;
use App\Models\User;
use App\Support\DebriefCatchUp;
use App\Support\MemberDashboard;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class DebriefsDueWidget extends DashboardSection
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.member.widgets.debriefs-due';

    protected function sectionData(): array
    {
        $user = auth()->user();
        assert($user instanceof User);

        $all = $this->outstanding($user);
        $rows = $all->take(MemberDashboard::PREVIEW_LIMIT)->values();

        return [
            'rows' => $rows,
            'total' => $all->count(),
            'previewLimit' => MemberDashboard::PREVIEW_LIMIT,
            'heading' => __('Debriefs due'),
            'icon' => Heroicon::OutlinedDocumentCheck,
            'iconColor' => Palette::uncertain(),
        ];
    }

    /**
     * @return Collection<int, Booking>
     */
    protected function outstanding(User $user): Collection
    {
        return app(DebriefCatchUp::class)->outstandingLedBy($user);
    }
}
