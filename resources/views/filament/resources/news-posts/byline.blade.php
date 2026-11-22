@php
    use App\Filament\Teams\Resources\Members\MemberResource;
    use App\Models\NewsPost;
    use App\Models\User;
    use Filament\Facades\Filament;
    use Filament\Support\Icons\Heroicon;

    /** @var NewsPost $post */
    $post ??= $getRecord();
    $author = $post->author;
    $timezone = auth()->user()?->timezone ?? config('app.timezone');
    $publishedAt = $post->published_at?->timezone($timezone);
    $avatarUrl = $author?->getFilamentAvatarUrl();
    $authorUrl = $author instanceof User
        ? MemberResource::personUrlFor($author, $post->team ?? Filament::getTenant())
        : null;
@endphp

<span class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm font-normal">
    <span class="inline-flex min-w-0 items-center gap-2.5">
        @if (filled($avatarUrl) && $author instanceof User)
            @if (filled($authorUrl))
                <a href="{{ $authorUrl }}" class="shrink-0">
                    <img src="{{ $avatarUrl }}" alt="{{ $author->name }}"
                        class="fi-avatar fi-circular size-8 max-w-none" width="32" height="32" />
                </a>
            @else
                <img src="{{ $avatarUrl }}" alt="{{ $author->name }}"
                    class="fi-avatar fi-circular size-8 max-w-none shrink-0" width="32" height="32" />
            @endif
        @endif

        @if ($author instanceof User)
            <span class="truncate text-gray-500 dark:text-gray-400">
                <span>{{ __('by') }}</span>
                @if (filled($authorUrl))
                    <a href="{{ $authorUrl }}"
                        class="font-semibold text-gray-950 hover:underline dark:text-white">{{ $author->name }}</a>
                @else
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $author->name }}</span>
                @endif
            </span>
        @else
            <span class="text-gray-500 dark:text-gray-400">{{ __('-') }}</span>
        @endif
    </span>

    @if ($publishedAt)
        <span class="hidden h-4 w-px shrink-0 bg-gray-200 sm:inline-block dark:bg-white/10" aria-hidden="true"></span>
        <span
            class="inline-flex items-center gap-1.5 text-gray-500 dark:text-gray-400"
            x-tooltip="{{ json_encode($publishedAt->format('j M Y, H:i')) }}"
        >
            <x-filament::icon :icon="Heroicon::OutlinedClock" class="size-4 shrink-0" />
            <span>{{ $publishedAt->diffForHumans() }}</span>
        </span>
    @else
        <span class="hidden h-4 w-px shrink-0 bg-gray-200 sm:inline-block dark:bg-white/10" aria-hidden="true"></span>
        <span class="inline-flex items-center gap-1.5 text-gray-500 dark:text-gray-400">
            <x-filament::icon :icon="Heroicon::OutlinedClock" class="size-4 shrink-0" />
            <span>{{ __('Not yet published') }}</span>
        </span>
    @endif
</span>
