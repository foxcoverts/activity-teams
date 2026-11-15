<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use App\Models\User;
use App\Support\Calendar\ScheduleCalendarEvents;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs\Tab;
use Livewire\Attributes\Url;
use Saade\FilamentFullCalendar\Data\DatesSetInfo;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Data\FetchInfo;
use Saade\FilamentFullCalendar\Toolbar\CalendarTool;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class ScheduleCalendarWidget extends FullCalendarWidget
{
    /**
     * @var list<string>
     */
    private const VIEWS = [
        'dayGridMonth',
        'timeGridWeek',
        'timeGridDay',
        'listMonth',
    ];

    protected bool $isReadOnly = true;

    protected ?string $heading = ':title';

    /** Kept in the query string so reload and browser back restore the view. */
    #[Url(as: 'view', history: false, except: '')]
    public string $calendarView = '';

    #[Url(as: 'date', history: false, except: '')]
    public string $calendarDate = '';

    public function getDefaultActiveTab(): ?string
    {
        return 'rota';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'rota' => Tab::make(__('Rota')),
            'all' => Tab::make(__('All')),
        ];
    }

    public function config(): array
    {
        $config = [
            'initialView' => $this->resolvedView(),
            'mobileInitialView' => $this->calendarView !== '' ? $this->resolvedView() : 'listMonth',
            'mobileBreakpoint' => 600,
            'firstDay' => 1,
            'navLinks' => true,
            // Grow with content so List (and other views) scroll with the page,
            // instead of a short aspect-ratio box with its own scrollbar.
            'height' => 'auto',
            // Section already supplies the card chrome; keep only a top rule on
            // the view so we do not nest a second bordered, rounded box.
            'borderlessX' => true,
            'borderlessBottom' => true,
            // Timed events otherwise render as uncoloured list-items in month view.
            'eventDisplay' => 'block',
            // Compact list-day labels (theme default is "November 13, 2026" + "Friday").
            'listDayFormat' => [
                'weekday' => 'short',
                'day' => 'numeric',
                'month' => 'short',
            ],
            'listDayAltFormat' => false,
        ];

        if ($this->calendarDate !== '') {
            $config['initialDate'] = $this->calendarDate;
        }

        return $config;
    }

    protected function onDatesSet(DatesSetInfo $info): void
    {
        $this->calendarView = in_array($info->view, self::VIEWS, true)
            ? $info->view
            : 'dayGridMonth';
        $this->calendarDate = $info->currentStart->toDateString();
    }

    private function resolvedView(): string
    {
        if (in_array($this->calendarView, self::VIEWS, true)) {
            return $this->calendarView;
        }

        return 'dayGridMonth';
    }

    /**
     * @return list<EventData>
     */
    public function fetchEvents(FetchInfo $info): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $tenant = Filament::getTenant();

        return (new ScheduleCalendarEvents(
            viewer: $user,
            team: $tenant instanceof Team ? $tenant : null,
            tab: $this->activeTab ?? 'rota',
        ))->events($info);
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
            function ({ event, el, isPast }) {
                const status = event.extendedProps.status;
                const isTask = event.extendedProps.kind === 'task';
                const fadeTask = isTask && (status === 'cancelled' || status === 'completed');
                const fadeBooking = ! isTask && (isPast || status === 'cancelled');

                if (fadeTask || fadeBooking) {
                    el.style.opacity = '0.5';
                }
            }
        JS;
    }

    /**
     * Tabs stay defined for activeTab / session state; the UI lives in the toolbar.
     *
     * @return array<CalendarTool>
     */
    protected function tools(): array
    {
        return [
            CalendarTool::make('rota')
                ->label(__('Rota'))
                ->hiddenLabel(false)
                ->jsHandler("\$wire.set('activeTab', 'rota')")
                ->activeJsExpression("\$wire.activeTab === 'rota'")
                ->toggle(),
            CalendarTool::make('all')
                ->label(__('All'))
                ->hiddenLabel(false)
                ->jsHandler("\$wire.set('activeTab', 'all')")
                ->activeJsExpression("\$wire.activeTab === 'all'")
                ->toggle(),
        ];
    }

    protected function toolbarButtons(): array
    {
        return [
            'start' => [['prev', 'next'], 'today'],
            'center' => [['rota', 'all']],
            'end' => [['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listMonth']],
        ];
    }
}
