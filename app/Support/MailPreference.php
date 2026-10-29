<?php

namespace App\Support;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Models\Booking;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;

final class MailPreference
{
    public static function allows(User $user, MailChannel $channel, ?Model $subject = null): bool
    {
        if ($channel->group()->alwaysOn()) {
            return true;
        }

        $parent = $channel->parent();

        if ($parent instanceof MailChannel && ! self::allows($user, $parent, $subject)) {
            return false;
        }

        return self::resolved($user, $channel, $subject);
    }

    public static function resolved(User $user, MailChannel $channel, ?Model $subject = null): bool
    {
        return self::layerValue($user, $channel, $subject)
            ?? self::inheritedValue($user, $channel, $subject);
    }

    public static function layerValue(User $user, MailChannel $channel, ?Model $subject = null): ?bool
    {
        if ($subject instanceof Model) {
            $setting = self::settingFor($user, $subject);

            return $setting?->preference($channel);
        }

        $value = $user->mail_preferences[$channel->value] ?? null;

        return is_bool($value) ? $value : null;
    }

    public static function inheritedValue(User $user, MailChannel $channel, ?Model $subject = null): bool
    {
        if ($subject instanceof Model) {
            return self::layerValue($user, $channel) ?? $channel->default();
        }

        return $channel->default();
    }

    /**
     * @return array<string, string>
     */
    public static function formState(User $user, MailGroup $group, ?Model $subject = null): array
    {
        $state = [];

        foreach ($group->shownChannels() as $channel) {
            $stored = self::layerValue($user, $channel, $subject);
            $state[$channel->formKey()] = $stored === null ? '' : ($stored ? '1' : '0');
        }

        return $state;
    }

    /**
     * Stored form values, with gated children shown as off when the master channel is off.
     *
     * @return array<string, string>
     */
    public static function displayState(User $user, MailGroup $group, ?Model $subject = null): array
    {
        return self::applyMasterDisplay($user, $group, self::formState($user, $group, $subject), $subject);
    }

    /**
     * @param  array<string, mixed>  $form
     * @return array<string, mixed>
     */
    public static function applyMasterDisplay(User $user, MailGroup $group, array $form, ?Model $subject = null): array
    {
        if (! self::formMasterOff($user, $group, $form, $subject)) {
            return $form;
        }

        foreach ($group->shownChannels() as $channel) {
            if ($channel->parent() instanceof MailChannel) {
                $form[$channel->formKey()] = '0';
            }
        }

        return $form;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function formMasterOff(User $user, MailGroup $group, array $form, ?Model $subject = null): bool
    {
        $master = $group->masterChannel();

        if (! $master instanceof MailChannel) {
            return false;
        }

        $raw = $form[$master->formKey()] ?? $form[$master->value] ?? '';

        if ($raw === '0' || $raw === false || $raw === 0) {
            return true;
        }

        if ($raw === '1' || $raw === true || $raw === 1) {
            return false;
        }

        return ! self::inheritedValue($user, $master, $subject);
    }

    /**
     * @param  array<string, mixed>  $form
     */
    public static function save(User $user, MailGroup $group, array $form, ?Model $subject = null): void
    {
        $masterOff = self::formMasterOff($user, $group, $form, $subject);
        $preferences = [];

        foreach ($group->shownChannels() as $channel) {
            if ($masterOff && $channel->parent() instanceof MailChannel) {
                continue;
            }

            $raw = $form[$channel->formKey()] ?? $form[$channel->value] ?? '';

            if ($raw === '' || $raw === null) {
                continue;
            }

            $preferences[$channel->value] = $raw === '1' || $raw === true || $raw === 1;
        }

        if ($subject instanceof Model) {
            if ($masterOff) {
                $existing = self::settingFor($user, $subject)?->preferences ?? [];

                foreach ($group->shownChannels() as $channel) {
                    if ($channel->parent() instanceof MailChannel && array_key_exists($channel->value, $existing)) {
                        $preferences[$channel->value] = $existing[$channel->value];
                    }
                }
            }

            self::saveSubject($user, $subject, $preferences);

            return;
        }

        self::saveGlobal($user, $group, $preferences, $masterOff);
    }

    public static function forgetSubject(User $user, Model $subject): void
    {
        NotificationSetting::query()->where([
            'user_id' => $user->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ])->delete();
    }

    public static function tokenFor(User $user): string
    {
        if ($user->exists && ! array_key_exists('mail_preference_token', $user->getAttributes())) {
            $user->refresh();
        }

        if (filled($user->mail_preference_token)) {
            return $user->mail_preference_token;
        }

        $user->forceFill(['mail_preference_token' => User::generateToken()])->save();

        return $user->mail_preference_token;
    }

    public static function manageUrl(User $user, MailGroup $group, ?Model $subject = null): string
    {
        self::tokenFor($user);

        if ($subject instanceof Booking) {
            return route('mail-preferences.booking', [
                'user' => $user,
                'booking' => $subject,
            ]);
        }

        return route('mail-preferences.group', [
            'user' => $user,
            'group' => $group,
        ]);
    }

    /**
     * @return array{manageNotificationsUrl: string, manageNotificationsLabel: string}
     */
    public static function footerViewData(User $user, MailGroup $group, ?Model $subject = null): array
    {
        return [
            'manageNotificationsUrl' => self::manageUrl($user, $group, $subject),
            'manageNotificationsLabel' => $subject instanceof Booking
                ? __('Manage notifications for this booking')
                : __('Manage notification settings'),
        ];
    }

    public static function withFooter(MailMessage $message, User $recipient, MailGroup $group, ?Model $subject = null): MailMessage
    {
        $message->viewData = array_merge($message->viewData, self::footerViewData($recipient, $group, $subject));
        $message->template('mail.notification');

        return $message;
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    private static function saveGlobal(User $user, MailGroup $group, array $preferences, bool $preserveGated = false): void
    {
        $current = $user->mail_preferences ?? [];

        foreach ($group->shownChannels() as $channel) {
            if ($preserveGated && $channel->parent() instanceof MailChannel) {
                continue;
            }

            unset($current[$channel->value]);
        }

        $current = array_merge($current, $preferences);

        $user->forceFill([
            'mail_preferences' => $current === [] ? null : $current,
        ])->save();
    }

    /**
     * @param  array<string, bool>  $preferences
     */
    private static function saveSubject(User $user, Model $subject, array $preferences): void
    {
        $query = [
            'user_id' => $user->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ];

        if ($preferences === []) {
            NotificationSetting::query()->where($query)->delete();

            return;
        }

        NotificationSetting::query()->updateOrCreate($query, [
            'preferences' => $preferences,
        ]);
    }

    private static function settingFor(User $user, Model $subject): ?NotificationSetting
    {
        if ($user->relationLoaded('notificationSettings')) {
            $match = $user->notificationSettings->first(
                fn (NotificationSetting $setting): bool => $setting->subject_type === $subject->getMorphClass()
                    && (string) $setting->subject_id === (string) $subject->getKey(),
            );

            return $match instanceof NotificationSetting ? $match : null;
        }

        return NotificationSetting::query()->where([
            'user_id' => $user->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ])->first();
    }
}
