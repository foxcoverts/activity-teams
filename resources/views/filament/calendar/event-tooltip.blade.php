@php
    /** @var list<array{icon: \Filament\Support\Icons\Heroicon|\BackedEnum|string, text: string}> $rows */
@endphp
<div class="fi-fc-event-tooltip">
    @foreach ($rows as $row)
        <div class="fi-fc-event-tooltip__row">
            <x-filament::icon
                :icon="$row['icon']"
                class="fi-fc-event-tooltip__icon"
            />
            <span>{{ $row['text'] }}</span>
        </div>
    @endforeach
</div>
