@php
    $showPhone = $phoneOnFile;
    $showEmergency = $emergencyAllowed;
    $defaultOpen = $showPhone ? 'phone' : ($showEmergency ? 'emergency' : null);
@endphp

<div
    class="booking-contact-details"
    x-data="{ open: @js($defaultOpen) }"
>
    <div class="booking-contact-details__list">
        @if ($showPhone)
            <div wire:key="contact-phone-panel">
                <button
                    type="button"
                    class="booking-contact-details__trigger"
                    x-on:click="open = open === 'phone' ? null : 'phone'"
                    x-bind:aria-expanded="open === 'phone'"
                >
                    <span class="booking-contact-details__chevron" aria-hidden="true">
                        <x-filament::icon
                            :icon="\Filament\Support\Icons\Heroicon::ChevronRight"
                            class="size-4 transition-transform"
                            x-bind:class="{ 'rotate-90': open === 'phone' }"
                        />
                    </span>
                    <span class="booking-contact-details__title">{{ __('Phone') }}</span>
                </button>

                <div class="booking-contact-details__body" x-show="open === 'phone'" x-cloak>
                    @if ($needsPhoneGate)
                        <p>
                            <strong class="text-gray-950 dark:text-white">{{ __('Notice:') }}</strong>
                            {{ __('You may only use these details to contact team members regarding legitimate team matters. Any other use of these contact details, no matter how well intended, will be in breach of UK data protection laws.') }}
                        </p>

                        <label class="booking-contact-details__gate">
                            <x-filament::input.checkbox
                                class="mt-0.5"
                                wire:change="acknowledge('{{ \App\Enums\PersonalDataSection::ContactDetails->value }}')" />
                            <span>{{ __('I have a legitimate reason to view these details') }}</span>
                        </label>
                    @elseif ($phoneAcknowledged)
                        <x-filament::link :href="'tel:' . preg_replace('/[^\d+]/', '', $phone)" :icon="\Filament\Support\Icons\Heroicon::Phone">
                            {{ $phone }}
                        </x-filament::link>
                    @endif
                </div>
            </div>
        @endif

        @if ($showEmergency)
            <div wire:key="contact-emergency-panel">
                <button
                    type="button"
                    class="booking-contact-details__trigger"
                    x-on:click="open = open === 'emergency' ? null : 'emergency'"
                    x-bind:aria-expanded="open === 'emergency'"
                >
                    <span class="booking-contact-details__chevron" aria-hidden="true">
                        <x-filament::icon
                            :icon="\Filament\Support\Icons\Heroicon::ChevronRight"
                            class="size-4 transition-transform"
                            x-bind:class="{ 'rotate-90': open === 'emergency' }"
                        />
                    </span>
                    <span class="booking-contact-details__title">{{ __('Emergency contact') }}</span>
                </button>

                <div class="booking-contact-details__body" x-show="open === 'emergency'" x-cloak>
                    @if ($needsEmergencyGate)
                        <p>
                            <strong class="text-gray-950 dark:text-white">{{ __('Notice:') }}</strong>
                            {{ __('You may only use these details to contact team members regarding legitimate team matters. Any other use of these contact details, no matter how well intended, will be in breach of UK data protection laws.') }}
                        </p>

                        <label class="booking-contact-details__gate">
                            <x-filament::input.checkbox
                                class="mt-0.5"
                                wire:change="acknowledge('{{ \App\Enums\PersonalDataSection::EmergencyContact->value }}')" />
                            <span>{{ __('I have a legitimate reason to view these details') }}</span>
                        </label>
                    @elseif ($emergencyOnFile && $emergencyAcknowledged)
                        <dl class="booking-contact-details__fields">
                            @if (filled($emergencyName))
                                <div class="booking-contact-details__field">
                                    <dt class="booking-contact-details__field-label">{{ __('Name') }}</dt>
                                    <dd>{{ $emergencyName }}</dd>
                                </div>
                            @endif

                            <div class="booking-contact-details__field">
                                <dt class="booking-contact-details__field-label">{{ __('Phone') }}</dt>
                                <dd>
                                    <x-filament::link :href="'tel:' . preg_replace('/[^\d+]/', '', $emergencyPhone)" :icon="\Filament\Support\Icons\Heroicon::Phone">
                                        {{ $emergencyPhone }}
                                    </x-filament::link>
                                </dd>
                            </div>
                        </dl>
                    @elseif (! $emergencyOnFile)
                        @if ($isManager)
                            <p>{{ __('No emergency contact on file.') }}</p>
                        @else
                            <div class="space-y-3">
                                <p>
                                    {{ __('No emergency contact on file. Ask a team manager — they may hold alternative details.') }}
                                </p>
                                @if ($leadersUrl)
                                    <p>
                                        <x-filament::link :href="$leadersUrl" :icon="\Filament\Support\Icons\Heroicon::OutlinedStar">
                                            {{ __('Team leaders') }}
                                        </x-filament::link>
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
