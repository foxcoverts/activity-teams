<div class="flex flex-col gap-3 text-sm">
    @forelse ($keys as $key)
        <section class="flex flex-col gap-1">
            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $key['name'] }}</h3>
            @if ($key['required_for'] !== [])
                <ul class="list-disc ps-5 text-gray-700 dark:text-gray-200">
                    @foreach ($key['required_for'] as $requirement)
                        <li>{{ $requirement }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @empty
        <p class="text-gray-500 dark:text-gray-400">{{ __('Opens no locks.') }}</p>
    @endforelse
</div>
