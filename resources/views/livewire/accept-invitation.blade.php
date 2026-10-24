<div class="space-y-6">
    <div class="rounded-xl bg-white p-6 shadow">
        <h1 class="text-xl font-semibold">
            @if ($invitation->isSiteInvitation())
                {{ __('Invitation to :app', ['app' => config('app.name')]) }}
            @else
                {{ __('Invitation to :team', ['team' => $invitation->team->name]) }}
            @endif
        </h1>

        @if ($declined)
            <p class="mt-2">
                @if ($invitation->isSiteInvitation())
                    {{ __('You have declined this invitation. The system administrators have been told.') }}
                @else
                    {{ __('You have declined this invitation. Your team manager has been told.') }}
                @endif
            </p>
        @elseif (! $valid)
            <p class="mt-2">
                @if ($invitation->isSiteInvitation())
                    {{ __('This invitation is no longer valid. Ask a system administrator to send you a new one.') }}
                @else
                    {{ __('This invitation is no longer valid. Ask your team manager to send you a new one.') }}
                @endif
            </p>
        @else
            <p class="mt-2">
                @if ($invitation->isSiteInvitation())
                    {{ __('You have been invited as :roles.', ['roles' => $invitation->siteAccessPhrase()]) }}
                @else
                    {{ __('You have been invited to join :team.', ['team' => $invitation->team->name, 'name' => $invitation->user->name]) }}
                @endif
            </p>
        @endif
    </div>

    @if ($valid)
        @if ($account)
            <div class="space-y-3 rounded-xl bg-white p-6 shadow">
                <h2 class="font-semibold">{{ __('Use your existing account') }}</h2>
                <p class="text-sm">
                    @if ($invitation->isSiteInvitation())
                        {{ __('You are signed in as :name. Accepting adds this access to your account.', ['name' => $account->name]) }}
                    @else
                        {{ __('You are signed in as :name. Accepting adds this team to your account.', ['name' => $account->name]) }}
                    @endif
                </p>
                <button type="button" wire:click="useMyAccount" class="w-full rounded bg-sky-600 px-4 py-3 text-white">{{ __('Accept with this account') }}</button>
                <button type="button" wire:click="signOutAndCreateAccount" class="w-full rounded border px-4 py-3">{{ __('Sign out and create a new account') }}</button>
            </div>
        @else
            <div class="space-y-2 rounded-xl bg-white p-6 shadow">
                <h2 class="font-semibold">{{ __('Already have an account?') }}</h2>
                <a href="{{ route('filament.member.auth.login') }}" class="text-sky-700 underline">{{ __('Sign in to accept with your existing account') }}</a>
            </div>
        @endif

        @unless ($account)
            <form wire:submit="createAccount" class="space-y-4 rounded-xl bg-white p-6 shadow">
                <h2 class="font-semibold">{{ __('Create an account') }}</h2>

                <label class="block text-sm font-medium">
                    {{ __('Name') }}
                    <input type="text" wire:model="name" class="mt-1 w-full rounded border px-3 py-2">
                    @error('name') <span class="text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm font-medium">
                    {{ __('Email address') }}
                    <input type="email" wire:model="email" class="mt-1 w-full rounded border px-3 py-2">
                    <span class="block text-xs font-normal">{{ __('You can use a different address from the one invited. If you do, you will need to verify it.') }}</span>
                    @error('email') <span class="text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm font-medium">
                    {{ __('Password') }}
                    <input type="password" wire:model="password" class="mt-1 w-full rounded border px-3 py-2">
                    @error('password') <span class="text-red-600">{{ $message }}</span> @enderror
                </label>

                <label class="block text-sm font-medium">
                    {{ __('Confirm password') }}
                    <input type="password" wire:model="password_confirmation" class="mt-1 w-full rounded border px-3 py-2">
                </label>

                <label class="block text-sm font-medium">
                    {{ __('Timezone') }}
                    <div class="relative mt-1">
                        <select wire:model="timezone"
                                x-init="const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                                    if (tz && $el.querySelector(`option[value='${tz}']`)) { $wire.timezone = tz }"
                                class="w-full appearance-none rounded border py-2 pl-3 pr-10">
                            @foreach ($this->timezoneGroups() as $region => $zones)
                                <optgroup label="{{ $region }}">
                                    @foreach ($zones as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    @error('timezone') <span class="text-red-600">{{ $message }}</span> @enderror
                </label>

                <div class="space-y-3 rounded border p-4">
                    <h3 class="font-medium">{{ __('Contact details') }}</h3>
                    <p class="text-sm">{{ __('Your phone number is shown to the lead instructor and to other people attending the same booking, until the end of that booking. Only share it if you are happy for that.') }}</p>
                    <label class="block text-sm font-medium">
                        {{ __('Phone') }}
                        <input type="tel" wire:model="phone" class="mt-1 w-full rounded border px-3 py-2">
                        @error('phone') <span class="text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="space-y-3 rounded border p-4">
                    <h3 class="font-medium">{{ __('Emergency contact') }}</h3>
                    <p class="text-sm">{{ __('Your emergency contact is shown only to people attending a booking you are attending, on the day of the booking, so they can reach someone if you cannot. Only give details of someone who knows you are sharing them.') }}</p>
                    <label class="block text-sm font-medium">
                        {{ __('Emergency contact name') }}
                        <input type="text" wire:model="emergency_name" class="mt-1 w-full rounded border px-3 py-2">
                        @error('emergency_name') <span class="text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-medium">
                        {{ __('Emergency contact phone') }}
                        <input type="tel" wire:model="emergency_phone" class="mt-1 w-full rounded border px-3 py-2">
                        @error('emergency_phone') <span class="text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>

                <button type="submit" class="w-full rounded bg-sky-600 px-4 py-3 text-white">{{ __('Create account') }}</button>
            </form>
        @endunless

        <div class="rounded-xl bg-white p-6 shadow">
            <button type="button" wire:click="decline" wire:confirm="{{ __('Decline this invitation?') }}" class="w-full rounded border px-4 py-3">{{ __('Decline invitation') }}</button>
        </div>
    @endif
</div>
