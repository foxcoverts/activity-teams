@php
    use Filament\Support\Icons\Heroicon;

    $user = auth()->user();
@endphp

@if ($user instanceof \App\Models\User && $user->profileConfirmationIsDue() && $user->hasDeferredProfileConfirmation())
    <div class="profile-confirmation-banner">
        <x-filament::icon :icon="Heroicon::OutlinedExclamationTriangle" class="size-5 shrink-0" />
        <p>
            {{ __('Please confirm your contact and emergency details.') }}
            <a href="{{ route('profile.confirm') }}" class="font-medium underline">{{ __('Review now') }}</a>
        </p>
    </div>
@endif
