@props([
    'people' => [],
])

@if (count($people) === 0)
    <p class="text-sm text-gray-500 dark:text-gray-400">-</p>
@else
    <ul class="invite-list">
        @foreach ($people as $person)
            <li class="invite-row">
                @if (filled($person['url'] ?? null))
                    <a href="{{ $person['url'] }}" class="invite-person">
                        @include('filament.bookings.person-identity', ['person' => $person])
                    </a>
                @else
                    <div class="invite-person">
                        @include('filament.bookings.person-identity', ['person' => $person])
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
