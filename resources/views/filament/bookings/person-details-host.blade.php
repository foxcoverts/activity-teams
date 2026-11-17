@php
    use App\Filament\Member\Resources\Qualifications\QualificationResource;
    use App\Support\BookingPeople;
    use Filament\Support\Icons\Heroicon;

    $tabs = array_values(array_filter([
        $showQualifications ? 'qualifications' : null,
        $showKeys ? 'keys' : null,
        $showContact ? 'contact' : null,
    ]));

    $defaultTab = in_array($tab, $tabs, true)
        ? $tab
        : ($tabs[0] ?? 'contact');

    $showTabBar = count($tabs) > 1;
@endphp

<div
    @class(['booking-person-details', 'booking-person-details--with-tabs' => $showTabBar])
    x-data="{ tab: @js($defaultTab) }">
    @if ($showTabBar)
        <div class="booking-person-details__tabs">
            <x-filament::tabs contained>
                @if ($showQualifications)
                    <x-filament::tabs.item
                        :icon="QualificationResource::ICON"
                        alpine-active="tab === 'qualifications'"
                        x-on:click="tab = 'qualifications'; $store.bookingPersonDetails.qualification = null">
                        {{ __('Qualifications') }}
                    </x-filament::tabs.item>
                @endif

                @if ($showKeys)
                    <x-filament::tabs.item
                        :icon="Heroicon::OutlinedKey"
                        alpine-active="tab === 'keys'"
                        x-on:click="tab = 'keys'; $store.bookingPersonDetails.qualification = null">
                        {{ __('Keys') }}
                    </x-filament::tabs.item>
                @endif

                @if ($showContact)
                    <x-filament::tabs.item
                        :icon="Heroicon::OutlinedPhoneArrowUpRight"
                        alpine-active="tab === 'contact'"
                        x-on:click="tab = 'contact'; $store.bookingPersonDetails.qualification = null">
                        {{ __('Contact') }}
                    </x-filament::tabs.item>
                @endif
            </x-filament::tabs>
        </div>
    @endif

    @if ($showQualifications)
        <div
            class="booking-person-details__panel"
            x-show="tab === 'qualifications'"
            @if ($defaultTab !== 'qualifications') x-cloak @endif>
            @include('filament.bookings.person-access', [
                'alpine' => true,
                'framed' => false,
                'access' => BookingPeople::panel($booking, $person, 'qualifications'),
            ])
        </div>
    @endif

    @if ($showKeys)
        <div
            class="booking-person-details__panel"
            x-show="tab === 'keys'"
            @if ($defaultTab !== 'keys') x-cloak @endif>
            @include('filament.bookings.person-access', [
                'alpine' => true,
                'framed' => false,
                'access' => BookingPeople::panel($booking, $person, 'keys'),
            ])
        </div>
    @endif

    @if ($showContact)
        <div
            class="booking-person-details__panel booking-person-details__panel--contact"
            x-show="tab === 'contact'"
            @if ($defaultTab !== 'contact') x-cloak @endif>
            <livewire:booking-contact-details :booking="$booking" :person="$person" :key="'contact-'.$person->getKey()" />
        </div>
    @endif
</div>
