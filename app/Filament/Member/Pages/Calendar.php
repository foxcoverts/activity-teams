<?php

namespace App\Filament\Member\Pages;

use App\Filament\Widgets\ScheduleCalendarWidget;
use App\Models\Booking;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Calendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Calendar;

    protected static ?string $slug = 'calendar';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament-panels::pages.page';

    public static function getNavigationLabel(): string
    {
        return __('Calendar');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Calendar');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('viewAny', Booking::class);
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            ScheduleCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
