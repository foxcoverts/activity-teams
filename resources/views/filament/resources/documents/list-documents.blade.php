@php
    use App\Support\Palette;
    use Illuminate\Support\Str;

    $table = $this->getTable();
    $activeFiltersCount = $table->getActiveFiltersCount();
    $filterCount = collect($table->getFilters())->filter(fn ($filter) => ! $filter->isHidden())->count();

    $filtersApplyAction = $table->getFiltersApplyAction();
    $filtersResetAction = $table->getFiltersResetAction();
    $filtersForm = $table->getFiltersForm();

    $records = $this->getTableRecords();
    $timezone = auth()->user()?->timezoneName() ?? (string) config('app.timezone');
@endphp
<x-filament-panels::page>

    @if ($filterCount)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('Filters') }}
                @if ($activeFiltersCount)
                    <x-filament::badge size="sm">
                        {{ $activeFiltersCount }}
                    </x-filament::badge>
                @endif
            </x-slot>
            <x-slot name="footer">
                <div class="flex justify-between gap-4">
                    {{ $filtersApplyAction }}
                    @if ($activeFiltersCount)
                        {{ $filtersResetAction }}
                    @endif
                </div>
            </x-slot>

            {{ $filtersForm }}
        </x-filament::section>
    @endif

    <div class="space-y-4">
        <x-filament-tables::search-field />

        @forelse ($records as $document)
            @php
                $url = $this->getResource()::getUrl('view', ['record' => $document], panel: 'teams', tenant: $document->team);
                $updatedAt = $document->updated_at?->timezone($timezone);
            @endphp

            <article>
                <x-filament::section>
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <a href="{{ $url }}" class="text-base font-semibold text-gray-950 hover:underline dark:text-white">
                            {{ $document->title }}
                        </a>
                        @if ($document->trashed())
                            <x-filament::badge :color="Palette::destructive()" size="sm">
                                {{ __('Trashed') }}
                            </x-filament::badge>
                        @endif
                    </div>

                    @if (filled($document->notes))
                        <div
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400 [&_p+p]:mt-2 [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-primary-600 [&_a]:underline [&_a]:underline-offset-4 hover:[&_a]:text-primary-500 active:[&_a]:text-primary-700 dark:[&_a]:text-primary-400 dark:hover:[&_a]:text-primary-300 dark:active:[&_a]:text-primary-200"
                        >
                            {!! Str::markdown($document->notes, [
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                            ]) !!}
                        </div>
                    @endif

                    @if ($updatedAt)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                            <span class="cursor-help" x-tooltip="{{ json_encode($updatedAt->format('j M Y, H:i')) }}">
                                {{ __('Updated :when', ['when' => $updatedAt->diffForHumans()]) }}
                            </span>
                        </p>
                    @endif
                </x-filament::section>
            </article>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No documents yet.') }}</p>
            </x-filament::section>
        @endforelse

        <x-filament::pagination :paginator="$records" />
    </div>

</x-filament-panels::page>
