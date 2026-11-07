<div class="profile-avatar">
    <x-filament-panels::avatar.user :user="$this->getUser()" size="lg" class="shrink-0" />

    <p class="min-w-0 flex-1 text-sm text-gray-500 dark:text-gray-400">
        {{ __('Your photo comes from Gravatar, using the email on this account. If you have not set a Gravatar photo, you see soft initials generated from your name. Changing your email changes which Gravatar is used.') }}
    </p>
</div>
