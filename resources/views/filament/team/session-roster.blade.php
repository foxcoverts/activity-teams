<section class="invite-block">
    <h3 class="invite-heading">{{ __('Session') }}</h3>

    <div class="invite-list">
        @forelse ($people as $person)
            <div class="invite-row" wire:key="session-{{ $person['id'] }}">
                <div class="invite-person">
                    <span>{{ $person['name'] }}</span>
                    @include('filament.bookings.person-badges', ['person' => $person, 'showQualified' => $showQualified, 'showKeys' => $showKeys])
                </div>
            </div>
        @empty
            <p class="invite-empty">{{ __('No one is on this session.') }}</p>
        @endforelse
    </div>

    @include('filament.bookings.person-access', ['access' => $access])
</section>
