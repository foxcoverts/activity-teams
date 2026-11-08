<span class="inline-flex items-center gap-3">
    <x-filament::avatar
        :src="$person->getFilamentAvatarUrl()"
        :alt="$person->name"
        size="sm"
    />
    <span>{{ $person->name }}</span>
</span>
