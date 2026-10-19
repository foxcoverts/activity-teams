<div class="flex flex-col gap-4">
    @forelse ($lines as $key)
        <section>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $key['name'] }}</h3>
            <ul class="mt-1 list-disc ps-5 text-sm text-gray-700 dark:text-gray-200">
                @foreach ($key['rings'] as $ring)
                    <li>{{ $ring }}</li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No keys required.') }}</p>
    @endforelse
</div>
