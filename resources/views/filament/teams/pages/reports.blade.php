@php
    use App\Enums\BookingStatus;
    use App\Support\ManagerBookingReports;
    use App\Support\Palette;
    use Filament\Support\Icons\Heroicon;

    $summary = $this->summary();
    $previousYear = $summary['previous_year'];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section :icon="Heroicon::OutlinedCalendarDays" :heading="__('Period')">
            <label class="block text-sm font-medium text-gray-950 dark:text-white" for="reports-year">
                {{ __('Calendar year') }}
            </label>
            <select id="reports-year" wire:model.live="year"
                class="mt-2 block w-full max-w-xs rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm outline outline-1 -outline-offset-1 outline-gray-950/10 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-600 dark:bg-white/5 dark:text-white dark:outline-white/10 sm:text-sm">
                @foreach ($this->availableYears() as $availableYear)
                    <option value="{{ $availableYear }}">{{ $availableYear }}</option>
                @endforeach
            </select>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Compared with :year.', ['year' => $previousYear]) }}
            </p>
        </x-filament::section>

        <x-filament::section :icon="Heroicon::OutlinedChartBar" :heading="__('Overview')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Confirmed'),
                    'metric' => $summary['confirmed'],
                    'previousYear' => $previousYear,
                ])
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Cancelled'),
                    'metric' => $summary['cancelled'],
                    'previousYear' => $previousYear,
                ])
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Cancellation rate'),
                    'metric' => $summary['cancellation_rate'],
                    'previousYear' => $previousYear,
                    'percent' => true,
                    'hint' => __('Of decided bookings (confirmed + cancelled).'),
                ])
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Scheduled hours'),
                    'metric' => $summary['scheduled_hours'],
                    'previousYear' => $previousYear,
                    'hours' => true,
                    'hint' => __('Sum of confirmed session lengths.'),
                ])
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Response rate'),
                    'metric' => $summary['response_rate'],
                    'previousYear' => $previousYear,
                    'percent' => true,
                ])
                @include('filament.teams.pages.reports.metric', [
                    'label' => __('Can-help rate'),
                    'metric' => $summary['can_help_rate'],
                    'previousYear' => $previousYear,
                    'percent' => true,
                ])
            </div>
        </x-filament::section>

        @if ($this->pastTentativeLiveCount() > 0 || $summary['past_tentative']['current'] > 0 || $summary['past_tentative']['previous'] > 0)
            <x-filament::section :icon="Heroicon::OutlinedQuestionMarkCircle" :heading="__('Past tentative bookings')"
                :description="__('Leftover drafts that ended without being confirmed or cancelled.')">
                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @include('filament.teams.pages.reports.metric', [
                        'label' => __('Past tentative in year'),
                        'metric' => $summary['past_tentative'],
                        'previousYear' => $previousYear,
                        'hint' => __('Bookings that started in that year and are still tentative after ending.'),
                    ])
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Live leftovers') }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($summary['past_tentative']['live']) }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ __('All past tentative bookings on this team right now.') }}
                        </p>
                    </div>
                </div>

                @if ($this->pastTentativeBookings()->isNotEmpty())
                    <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Past tentative bookings that started in :year.', ['year' => $this->year]) }}
                    </p>
                    @include('filament.teams.widgets.manager.booking-days', [
                        'bookings' => $this->pastTentativeBookings(),
                    ])
                    <div class="mt-4">
                        <a href="{{ $this->historyBookingsUrl(BookingStatus::Tentative) }}"
                            class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('See tentative bookings in history') }}
                        </a>
                    </div>
                @endif
            </x-filament::section>
        @endif

        <x-filament::section :icon="Heroicon::OutlinedTag" :heading="__('By activity')">
            @include('filament.teams.pages.reports.breakdown-table', [
                'rows' => $this->activityBreakdown(),
                'heading' => __('Activity'),
                'dimension' => 'activity',
                'year' => $this->year,
            ])
        </x-filament::section>

        <x-filament::section :icon="Heroicon::OutlinedUsers" :heading="__('By audience')">
            @include('filament.teams.pages.reports.breakdown-table', [
                'rows' => $this->audienceBreakdown(),
                'heading' => __('Audience'),
                'dimension' => 'audience',
                'year' => $this->year,
            ])
        </x-filament::section>

        <x-filament::section :icon="Heroicon::OutlinedMapPin" :heading="__('By venue')">
            @include('filament.teams.pages.reports.breakdown-table', [
                'rows' => $this->venueBreakdown(),
                'heading' => __('Venue'),
                'dimension' => 'venue',
                'year' => $this->year,
            ])
        </x-filament::section>

        @if ($this->yearTrend() !== [])
            <x-filament::section :icon="Heroicon::OutlinedCalendarDays" :heading="__('Recent years')"
                :description="__('Confirmed and cancelled totals for years with bookings, looking back up to :years years through :year.', [
                    'years' => ManagerBookingReports::TREND_YEARS,
                    'year' => $this->year,
                ])">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[24rem] table-fixed text-sm">
                        <colgroup>
                            <col class="w-[34%]" />
                            <col class="w-[33%]" />
                            <col class="w-[33%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th scope="col" class="py-2 pr-3 text-start font-medium">{{ __('Year') }}</th>
                                <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Confirmed') }}</th>
                                <th scope="col" class="py-2 pl-2 text-end font-medium">{{ __('Cancelled') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($this->yearTrend() as $row)
                                <tr wire:key="report-trend-{{ $row['year'] }}">
                                    <td class="py-2 pr-3 font-medium text-gray-950 dark:text-white">{{ $row['year'] }}</td>
                                    <td class="py-2 px-2 text-end tabular-nums">{{ number_format($row['confirmed']) }}</td>
                                    <td class="py-2 pl-2 text-end tabular-nums">{{ number_format($row['cancelled']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section :icon="Heroicon::OutlinedUserGroup" :heading="__('Member engagement')"
            :description="__('Current members invited to non-cancelled bookings in :year, ordered by can-help then other responses. Roll-call rate only where attendance was recorded.', ['year' => $this->year])">
            @php
                $engagement = $this->memberEngagement();
                $maxInvites = (int) $engagement->max('invites');
            @endphp
            @if ($engagement->isEmpty())
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('This team has no members yet.') }}</p>
            @else
                <div class="mb-3 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm"
                            style="background-color: {{ Palette::positive()[500] }}"></span>
                        {{ __('Can help') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm"
                            style="background-color: {{ Palette::uncertain()[500] }}"></span>
                        {{ __('Maybe') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm"
                            style="background-color: {{ Palette::destructive()[500] }}"></span>
                        {{ __('Cannot make it') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm"
                            style="background-color: {{ Palette::neutral()[500] }}"></span>
                        {{ __('Awaiting response') }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] table-fixed text-sm">
                        <colgroup>
                            <col class="w-[28%]" />
                            <col class="w-[10%]" />
                            <col class="w-[34%]" />
                            <col class="w-[14%]" />
                            <col class="w-[14%]" />
                        </colgroup>
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <th scope="col" class="py-2 pr-3 text-start font-medium">{{ __('Member') }}</th>
                                <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Invites') }}</th>
                                <th scope="col" class="py-2 px-2 text-start font-medium">{{ __('Response') }}</th>
                                <th scope="col" class="py-2 px-2 text-end font-medium">{{ __('Roll-call') }}</th>
                                <th scope="col" class="py-2 pl-2 text-end font-medium">{{ __('Led') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($engagement as $row)
                                <tr wire:key="report-member-{{ $row['user']->getKey() }}">
                                    <td class="py-2 pr-3 align-middle">
                                        <a href="{{ $this->memberUrl($row['user']) }}"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $row['user']->name }}
                                        </a>
                                    </td>
                                    <td class="py-2 px-2 text-end align-middle tabular-nums">{{ number_format($row['invites']) }}</td>
                                    <td class="py-2 px-2 align-middle">
                                        @include('filament.teams.pages.reports.response-bar', [
                                            'row' => $row,
                                            'maxInvites' => $maxInvites,
                                        ])
                                    </td>
                                    <td class="py-2 px-2 text-end align-middle tabular-nums">
                                        {{ $row['rollcall_rate'] === null ? '—' : number_format($row['rollcall_rate'] * 100, 0).'%' }}
                                    </td>
                                    <td class="py-2 pl-2 text-end align-middle tabular-nums">{{ number_format($row['leads']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <div>
            <a href="{{ $this->historyBookingsUrl(BookingStatus::Cancelled) }}"
                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                {{ __('Browse cancelled bookings in history') }}
            </a>
        </div>
    </div>
</x-filament-panels::page>
