@php
    use Illuminate\Support\HtmlString;
    use App\Enums\NewsPostStatus;
    use Filament\Support\Enums\IconSize;
    use Filament\Schemas\Schema;
    use Filament\Support\Enums\Width;

    $table = $this->getTable();
    $activeFiltersCount = $table->getActiveFiltersCount();
    $filterCount = collect($table->getFilters())->filter(fn($filter) => !$filter->isHidden())->count();

    $filtersApplyAction = $table->getFiltersApplyAction();
    $filtersResetAction = $table->getFiltersResetAction();
    $filtersForm = $table->getFiltersForm();
@endphp
<x-filament-panels::page>

    @if ($filterCount)
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                {{ __('Filters') }}
                <x-filament::badge color="amber" size="sm">{{ $filterCount }}</x-filament::badge>
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

    <div class="space-y-6">
        @forelse ($this->getTableRecords() as $post)
            @php
                $url = $this->getResource()::getUrl('view', ['record' => $post], panel: 'teams', tenant: $post->team);
                $publishedAt = $post->published_at?->timezone(auth()->user()?->timezone ?? config('app.timezone'));
            @endphp

            <article>
                <x-filament::section>
                    <x-slot name="heading">
                        <a href="{{ $url }}" class="hover:underline">{{ $post->title }}</a>
                    </x-slot>
                    <x-slot name="description">
                        {{ __('By :author', ['author' => $post->author->name]) }}
                        @if ($publishedAt)
                            · <span class="cursor-help"
                                x-tooltip="{{ json_encode($publishedAt->format('j M Y, H:i')) }}">
                                {{ __('Posted :when', ['when' => $publishedAt->diffForHumans()]) }}</span>
                        @else
                            · {{ __('Not yet published') }}
                        @endif
                    </x-slot>
                    @if (auth()->user()->can('update', $post))
                        <x-slot name="afterHeader">
                            <x-filament::badge :color="$post->getStatus()->getColor()" :icon="$post->getStatus()->getIcon()">
                                {{ $post->getStatus()->getLabel() }}
                            </x-filament::badge>
                        </x-slot>
                    @endif

                    <p>{{ $post->excerpt() }}</p>
                    <x-filament::link :href="$url">
                        {{ __('Read more...') }}
                    </x-filament::link>
                </x-filament::section>
            </article>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('No news has been published yet.') }}</p>
            </x-filament::section>
        @endforelse

        <x-filament::pagination :paginator="$this->getTableRecords()" />

    </div>

</x-filament-panels::page>
