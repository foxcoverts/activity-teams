<form wire:submit="save" class="space-y-4 rounded-xl bg-white p-6 shadow">
    <h1 class="text-xl font-semibold">{{ __('Setup your account') }}</h1>

    <label class="block text-sm font-medium">
        {{ __('Name') }}
        <input type="text" wire:model="name" class="mt-1 w-full rounded border px-3 py-2">
        @error('name') <span class="text-red-600">{{ $message }}</span> @enderror
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

    <button type="submit" class="w-full rounded bg-sky-600 px-4 py-3 text-white">{{ __('Save and continue') }}</button>
</form>
