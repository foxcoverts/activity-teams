@php
    /** @var \Illuminate\Support\Collection<int, array{team: \App\Models\Team, role: ?\App\Enums\MembershipRole, url: string}> $teams */
@endphp

<x-filament-widgets::widget :class="!$failed && $teams->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($teams->isNotEmpty())
        <h2 class="mb-3 text-sm font-semibold text-gray-500 dark:text-gray-400">{{ __('My Teams') }}</h2>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teams as $item)
                @php
                    $team = $item['team'];
                    $role = $item['role'];
                @endphp
                <a
                    href="{{ $item['url'] }}"
                    class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition hover:bg-gray-50 dark:bg-gray-900 dark:ring-white/10 dark:hover:bg-white/5">
                    <img
                        src="{{ $team->getFilamentAvatarUrl() }}"
                        alt=""
                        class="size-10 shrink-0 rounded-lg object-cover"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium text-gray-950 dark:text-white">{{ $team->name }}</div>
                        @if ($role)
                            <div class="mt-1">
                                <x-filament::badge :color="$role->getColor()" :icon="$role->getIcon()">
                                    {{ $role->getLabel() }}
                                </x-filament::badge>
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
