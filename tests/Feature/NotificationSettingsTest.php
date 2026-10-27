<?php

namespace Tests\Feature;

use App\Actions\OverrideAttendanceAction;
use App\Actions\PassKeyRingAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Events\BookingChanged;
use App\Events\BookingInvite;
use App\Events\LeadInstructorCleared;
use App\Filament\Member\Pages\NotificationSettings;
use App\Jobs\SendBookingNotification;
use App\Livewire\ManageMailPreferences;
use App\Mail\AttendanceRecordedMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingInviteMail;
use App\Mail\LeadNeededMail;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Key;
use App\Models\KeyRing;
use App\Models\NotificationSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\KeyRingPassedFromNotification;
use App\Notifications\KeyRingPassedToNotification;
use App\Notifications\KeyRingReturnedToCupboardNotification;
use App\Support\MailPreference;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_overrides_global_which_overrides_the_system_default(): void
    {
        $user = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();

        $this->assertTrue(MailPreference::allows($user, MailChannel::BookingsMail, $booking));

        MailPreference::save($user, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        $this->assertFalse(MailPreference::allows($user->fresh(), MailChannel::BookingsMail, $booking));

        MailPreference::save($user->fresh(), MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '1',
        ], $booking);

        $this->assertTrue(MailPreference::allows($user->fresh(), MailChannel::BookingsMail, $booking));
        $this->assertFalse(MailPreference::allows($user->fresh(), MailChannel::BookingsMail));
    }

    public function test_updates_are_off_when_all_mail_is_off_even_if_updates_are_on(): void
    {
        $user = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();

        MailPreference::save($user, MailGroup::Bookings, [
            MailChannel::BookingsUpdates->value => '1',
        ], $booking);
        MailPreference::save($user->fresh(), MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        $setting = NotificationSetting::query()->where('user_id', $user->getKey())->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->preference(MailChannel::BookingsUpdates));
        $this->assertFalse(MailPreference::allows($user->fresh(), MailChannel::BookingsUpdates, $booking));
    }

    public function test_gated_booking_channels_show_off_when_all_mail_is_off_and_keep_stored_values(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsComments->value => '1',
        ], $booking);

        Livewire::test(ManageMailPreferences::class, [
            'user' => $attendee,
            'booking' => $booking,
        ])
            ->assertSet('preferences.'.MailChannel::BookingsComments->formKey(), '1')
            ->set('preferences.'.MailChannel::BookingsMail->formKey(), '0')
            ->assertSet('preferences.'.MailChannel::BookingsUpdates->formKey(), '0')
            ->assertSet('preferences.'.MailChannel::BookingsComments->formKey(), '0')
            ->call('save');

        $setting = NotificationSetting::query()->where('user_id', $attendee->getKey())->first();

        $this->assertNotNull($setting);
        $this->assertFalse($setting->preference(MailChannel::BookingsMail));
        $this->assertTrue($setting->preference(MailChannel::BookingsComments));
        $this->assertFalse(MailPreference::allows($attendee->fresh(), MailChannel::BookingsComments, $booking));
    }

    public function test_the_notifications_page_shows_gated_channels_as_off_when_all_mail_is_off(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);

        Livewire::test(NotificationSettings::class)
            ->fillForm([
                MailChannel::BookingsComments->formKey() => '1',
            ])
            ->call('save');

        $this->assertTrue($user->fresh()->mail_preferences[MailChannel::BookingsComments->value]);

        Livewire::test(NotificationSettings::class)
            ->assertFormFieldEnabled(MailChannel::BookingsUpdates->formKey())
            ->assertFormFieldEnabled(MailChannel::BookingsComments->formKey())
            ->assertSet('data.'.MailChannel::BookingsComments->formKey(), '1')
            ->set('data.'.MailChannel::BookingsMail->formKey(), '0')
            ->assertSet('data.'.MailChannel::BookingsUpdates->formKey(), '0')
            ->assertSet('data.'.MailChannel::BookingsComments->formKey(), '0')
            ->assertFormFieldDisabled(MailChannel::BookingsUpdates->formKey())
            ->assertFormFieldDisabled(MailChannel::BookingsComments->formKey())
            ->set('data.'.MailChannel::BookingsMail->formKey(), '')
            ->assertSet('data.'.MailChannel::BookingsComments->formKey(), '1')
            ->assertFormFieldEnabled(MailChannel::BookingsUpdates->formKey())
            ->assertFormFieldEnabled(MailChannel::BookingsComments->formKey());

        Livewire::test(NotificationSettings::class)
            ->fillForm([
                MailChannel::BookingsMail->formKey() => '0',
                MailChannel::BookingsComments->formKey() => '1',
            ])
            ->call('save')
            ->assertSet('data.'.MailChannel::BookingsUpdates->formKey(), '0')
            ->assertSet('data.'.MailChannel::BookingsComments->formKey(), '0')
            ->assertFormFieldDisabled(MailChannel::BookingsUpdates->formKey())
            ->assertFormFieldDisabled(MailChannel::BookingsComments->formKey());

        $user->refresh();

        $this->assertFalse($user->mail_preferences[MailChannel::BookingsMail->value]);
        $this->assertTrue($user->mail_preferences[MailChannel::BookingsComments->value]);
        $this->assertFalse(MailPreference::allows($user, MailChannel::BookingsComments));
    }

    public function test_all_mail_off_skips_invite_update_and_attendance_mail_but_still_records_attendance(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));
        event(new BookingChanged($booking, $actor));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertNotSent(BookingInviteMail::class);
        Mail::assertNotSent(BookingChangedMail::class);

        app(OverrideAttendanceAction::class)(
            $booking,
            $attendee,
            $actor,
            BookingAttendeeStatus::Accepted,
        );

        $this->assertSame(
            BookingAttendeeStatus::Accepted,
            $booking->attendees()->find($attendee)->attendance->status,
        );
        Mail::assertNotQueued(AttendanceRecordedMail::class);
    }

    public function test_updates_off_skips_change_mail_but_still_sends_invites(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsUpdates->value => '0',
        ]);

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingInviteMail::class, 1);

        Queue::fake();
        Mail::fake();

        event(new BookingChanged($booking, $actor));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertNotSent(BookingChangedMail::class);
    }

    public function test_lead_needed_mail_still_sends_when_booking_mail_is_off_and_has_no_preferences_footer(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();
        $otherManager = User::factory()->teamManager($booking->team)->create();

        MailPreference::save($otherManager, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        Mail::fake();

        event(new LeadInstructorCleared($booking, $attendee, $actor));

        Mail::assertQueued(LeadNeededMail::class, fn (LeadNeededMail $mail): bool => $mail->hasTo($otherManager->email));

        $html = (new LeadNeededMail($booking, $attendee))->render();

        $this->assertStringNotContainsString('Manage notifications', $html);
    }

    public function test_optional_booking_mail_includes_a_preferences_footer_without_list_unsubscribe(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();
        MailPreference::tokenFor($attendee);

        $url = route('mail-preferences.booking', ['user' => $attendee, 'booking' => $booking]);
        $invite = new BookingInviteMail($booking, $attendee);
        $changed = new BookingChangedMail($booking, $attendee);
        $attendance = new AttendanceRecordedMail($booking, $attendee);

        foreach ([$invite, $changed, $attendance] as $mail) {
            $mail->assertSeeInOrderInHtml([
                'Thanks',
                'href="'.$url.'"',
                'Manage notifications for this booking',
            ], false);
            $mail->assertSeeInText('Manage notifications for this booking: '.$url);
            $this->assertArrayNotHasKey('List-Unsubscribe', $mail->headers()->text);
        }
    }

    public function test_a_preferences_token_cannot_open_another_persons_page(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();
        $other = User::factory()->teamMember()->create();
        MailPreference::tokenFor($other);

        $this->get(route('mail-preferences.booking', ['user' => $other, 'booking' => $booking]))
            ->assertForbidden();

        $this->get('/mail-preferences/not-a-real-token/booking/'.$booking->getKey())
            ->assertForbidden();
    }

    public function test_the_manage_page_does_not_write_on_get_and_subject_save_creates_an_override(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();
        MailPreference::tokenFor($attendee);

        $this->get(route('mail-preferences.booking', ['user' => $attendee, 'booking' => $booking]))
            ->assertOk()
            ->assertSee('This booking')
            ->assertSee('All bookings');

        $this->assertDatabaseCount('notification_settings', 0);

        Livewire::test(ManageMailPreferences::class, [
            'user' => $attendee,
            'booking' => $booking,
        ])
            ->assertSet('useGlobal', false)
            ->set('preferences.'.MailChannel::BookingsMail->formKey(), '0')
            ->call('save')
            ->assertSet('saved', true);

        $this->assertDatabaseHas('notification_settings', [
            'user_id' => $attendee->getKey(),
            'subject_id' => $booking->getKey(),
        ]);
        $this->assertNull($attendee->fresh()->mail_preferences);
    }

    public function test_saving_global_from_the_manage_page_does_not_create_a_subject_row(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        Livewire::test(ManageMailPreferences::class, [
            'user' => $attendee,
            'booking' => $booking,
        ])
            ->set('useGlobal', true)
            ->set('preferences.'.MailChannel::BookingsMail->formKey(), '0')
            ->call('save');

        $this->assertDatabaseCount('notification_settings', 0);
        $this->assertFalse($attendee->fresh()->mail_preferences[MailChannel::BookingsMail->value]);
    }

    public function test_a_member_can_save_and_reset_global_settings_from_the_notifications_page(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);

        $this->assertFalse(NotificationSettings::shouldRegisterNavigation());

        Livewire::test(NotificationSettings::class)
            ->fillForm([
                MailChannel::BookingsMail->formKey() => '0',
                MailChannel::KeysMail->formKey() => '0',
            ])
            ->call('save')
            ->assertNotified();

        $user->refresh();

        $this->assertFalse($user->mail_preferences[MailChannel::BookingsMail->value]);
        $this->assertFalse($user->mail_preferences[MailChannel::KeysMail->value]);

        Livewire::test(NotificationSettings::class)
            ->call('resetDefaults')
            ->assertNotified();

        $this->assertNull($user->fresh()->mail_preferences);
    }

    public function test_the_notifications_page_lists_an_override_and_removing_it_restores_inherit(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($attendee);

        $setting = NotificationSetting::query()->where('user_id', $attendee->getKey())->first();

        Livewire::test(NotificationSettings::class)
            ->assertSee('Overrides')
            ->assertSee('These apply to one booking. Remove an override to inherit your global settings again.')
            ->assertCanSeeTableRecords([$setting])
            ->assertDontSee('Add')
            ->callTableAction('delete', $setting)
            ->assertNotified();

        $this->assertDatabaseCount('notification_settings', 0);
        $this->assertTrue(MailPreference::allows($attendee->fresh(), MailChannel::BookingsMail, $booking));
    }

    public function test_notifications_is_in_the_user_menu_not_the_sidebar(): void
    {
        $user = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);

        $menuItem = Filament::getCurrentOrDefaultPanel()->getUserMenuItems()['notificationSettings'] ?? null;

        $this->assertNotNull($menuItem);
        $this->assertSame(__('Notifications'), $menuItem->getLabel());
        $this->assertSame(NotificationSettings::getUrl(panel: 'member'), $menuItem->getUrl());
    }

    public function test_one_users_preferences_do_not_affect_another(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();
        $other = User::factory()->teamMember()->create();
        $booking->attendees()->attach($other->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));
        event(new BookingInvite($booking, $other));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingInviteMail::class, fn (BookingInviteMail $mail): bool => $mail->hasTo($other->email));
        Mail::assertNotSent(BookingInviteMail::class, fn (BookingInviteMail $mail): bool => $mail->hasTo($attendee->email));
    }

    public function test_key_holder_mail_is_skipped_when_keys_mail_is_off_and_cupboard_manager_mail_still_sends(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $actor = User::factory()->teamManager($team)->create();
        $otherManager = User::factory()->teamManager($team)->create();
        $holder = User::factory()->teamMember($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);

        MailPreference::save($receiver, MailGroup::Keys, [
            MailChannel::KeysMail->value => '0',
        ]);
        MailPreference::save($otherManager, MailGroup::Keys, [
            MailChannel::KeysMail->value => '0',
        ]);

        (new PassKeyRingAction)($actor, $ring, $receiver->getKey());

        Notification::assertNotSentTo($receiver, KeyRingPassedToNotification::class);
        Notification::assertSentTo($holder, KeyRingPassedFromNotification::class);

        Notification::fake();

        (new PassKeyRingAction)($actor, $ring->fresh(), null);

        Notification::assertNotSentTo($receiver, KeyRingPassedFromNotification::class);
        Notification::assertSentTo($otherManager, KeyRingReturnedToCupboardNotification::class);
    }

    public function test_key_holder_mail_includes_a_preferences_footer(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);
        MailPreference::tokenFor($receiver);

        $url = route('mail-preferences.group', ['user' => $receiver, 'group' => MailGroup::Keys]);
        $message = (new KeyRingPassedToNotification($ring, $holder))->toMail($receiver);
        $html = (string) $message->render();
        $thanks = strpos($html, 'Thanks');
        $href = strpos($html, 'href="'.$url.'"');

        $this->assertNotFalse($thanks);
        $this->assertNotFalse($href);
        $this->assertLessThan($href, $thanks);
        $this->assertStringContainsString('Manage notification settings', $html);
    }

    /**
     * @return array{0: Booking, 1: User, 2: User}
     */
    private function bookingWithAttendee(): array
    {
        $actor = User::factory()->teamManager()->create();
        $attendee = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        return [$booking, $attendee, $actor];
    }

    /**
     * @return array{0: Team, 1: Key}
     */
    private function climbingTeam(): array
    {
        $team = Team::factory()->create();
        $activity = Activity::factory()->create(['name' => 'Scout climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);

        $key = Key::factory()->create(['name' => 'Climbing store']);
        $key->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);

        return [$team, $key];
    }

    private function heldRing(User $holder, Key $key): KeyRing
    {
        $ring = KeyRing::factory()->heldBy($holder)->create(['name' => 'Shared climbing']);
        $ring->keys()->attach($key);

        return $ring;
    }
}
