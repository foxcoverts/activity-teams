<?php

namespace Tests\Feature;

use App\Actions\RespondToBookingAction;
use App\Actions\UpdateBookingStatusAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Jobs\SendBookingNotification;
use App\Livewire\RespondToBooking;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingInviteMail;
use App\Mail\LeadNeededMail;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Qualification;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\LeadNeededNotification;
use App\Support\BookingMailThread;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_a_new_booking_is_tentative_and_cannot_be_created_confirmed(): void
    {
        $manager = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $audienceId = ActivityAudience::factory()->for($activity)->create()->audience_id;

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
                'venue_id' => $venue->id,
                'start_date' => '2026-10-24',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
                'group_name' => '1st Anytown Scouts',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(BookingStatus::Tentative, Booking::query()->first()->status);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'audience_id' => $audienceId,
                'venue_id' => $venue->id,
                'start_date' => '2026-10-25',
                'start_time' => '09:30',
                'end_time' => '11:30',
                'timezone' => 'Europe/London',
                'group_name' => '2nd Anytown Scouts',
            ])
            ->set('data.status', BookingStatus::Confirmed->value)
            ->call('create')
            ->assertHasErrors(['status']);

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_confirm_requires_an_attending_lead(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create();
        $waiting = User::factory()->create();
        $this->attend($booking, $waiting, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($manager);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionDisabled('confirmBooking');

        try {
            app(UpdateBookingStatusAction::class)->confirm($booking, $manager, $waiting->id, [
                'group_name' => $booking->group_name,
            ]);
            $this->fail('A lead who is not attending was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lead_instructor_id', $exception->errors());
        }
    }

    public function test_confirm_and_change_lead_say_when_no_one_is_attending(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create();

        $this->actingAs($manager);

        $message = 'No one is attending.';

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionDisabled('confirmBooking')
            ->assertActionExists('confirmBooking', fn (Action $action): bool => $action->getTooltip() === $message)
            ->assertActionDisabled('changeLead')
            ->assertActionExists('changeLead', fn (Action $action): bool => $action->getTooltip() === $message);

        $this->attend($booking, User::factory()->create(), BookingAttendeeStatus::Declined);
        $this->attend($booking, User::factory()->create(), BookingAttendeeStatus::Tentative);
        $this->attend($booking, User::factory()->create(), BookingAttendeeStatus::NeedsAction);

        Livewire::test(EditBooking::class, ['record' => $booking->fresh()->getRouteKey()])
            ->assertActionDisabled('confirmBooking')
            ->assertActionExists('confirmBooking', fn (Action $action): bool => $action->getTooltip() === $message)
            ->assertActionDisabled('changeLead')
            ->assertActionExists('changeLead', fn (Action $action): bool => $action->getTooltip() === $message);

        $this->attend($booking, User::factory()->create(), BookingAttendeeStatus::Accepted);

        Livewire::test(EditBooking::class, ['record' => $booking->fresh()->getRouteKey()])
            ->assertActionEnabled('confirmBooking')
            ->assertActionExists('confirmBooking', fn (Action $action): bool => $action->getTooltip() === null)
            ->assertActionEnabled('changeLead')
            ->assertActionExists('changeLead', fn (Action $action): bool => $action->getTooltip() === null);
    }

    public function test_the_roster_marks_who_qualifies_and_still_includes_everyone(): void
    {
        $manager = User::factory()->teamManager()->create();
        $qualified = User::factory()->teamMember()->create(['name' => 'Sam Holder']);
        $other = User::factory()->teamMember()->create(['name' => 'Alex Guest']);
        $activity = Activity::factory()->create();
        ActivityAudience::factory()->for($activity)->create()->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $booking = Booking::factory()->create(['activity_id' => $activity->getKey()]);
        Qualification::factory()->forMember($qualified)->mountainTraining()->create();
        $this->attend($booking, $qualified, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $other, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking->fresh(),
            'pageClass' => ViewBooking::class,
        ])
            ->assertSee('Qualified')
            ->assertSee('Sam Holder')
            ->assertSee('Alex Guest')
            ->assertTableActionVisible('invite');

        $open = Booking::factory()->create();
        $this->attend($open, $other, BookingAttendeeStatus::NeedsAction);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $open->fresh(),
            'pageClass' => ViewBooking::class,
        ])
            ->assertDontSee('Qualified')
            ->assertSee('Alex Guest');
    }

    public function test_only_a_qualified_attending_person_can_lead(): void
    {
        $manager = User::factory()->teamManager()->create();
        $qualified = User::factory()->create(['name' => 'Qualified Lead']);
        $attending = User::factory()->create(['name' => 'Attending Only']);
        $activity = Activity::factory()->create();
        ActivityAudience::factory()->for($activity)->create()->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $booking = Booking::factory()->create(['activity_id' => $activity->getKey()]);
        Qualification::factory()->forMember($qualified)->mountainTraining()->create();
        $this->attend($booking, $qualified, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $attending, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        $this->assertSame(
            [__('Adult') => [$qualified->getKey() => 'Qualified Lead']],
            $booking->fresh()->leadOptions(),
        );

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionEnabled('confirmBooking');

        try {
            app(UpdateBookingStatusAction::class)->confirm($booking, $manager, $attending->id, [
                'group_name' => $booking->group_name,
            ]);
            $this->fail('An unqualified lead was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The lead instructor must be qualified for this activity.'],
                $exception->errors()['lead_instructor_id'],
            );
        }

        $unqualifiedOnly = Booking::factory()->create(['activity_id' => $activity->getKey()]);
        $this->attend($unqualifiedOnly, $attending, BookingAttendeeStatus::Accepted);

        Livewire::test(EditBooking::class, ['record' => $unqualifiedOnly->getRouteKey()])
            ->assertActionDisabled('confirmBooking')
            ->assertActionExists('confirmBooking', fn (Action $action): bool => $action->getTooltip() === 'No one attending is qualified to lead this activity.');
    }

    public function test_confirm_lists_only_accepted_attendees_as_leads(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create();
        $accepted = User::factory()->create(['name' => 'Accepted Lead']);
        $alsoAccepted = User::factory()->create(['name' => 'Also Accepted']);
        $waiting = User::factory()->create(['name' => 'Waiting Lead']);
        $this->attend($booking, $accepted, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $alsoAccepted, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $waiting, BookingAttendeeStatus::NeedsAction);
        $this->attend($booking, User::factory()->create(['name' => 'Maybe Person']), BookingAttendeeStatus::Tentative);
        $this->attend($booking, User::factory()->create(['name' => 'Declined Person']), BookingAttendeeStatus::Declined);
        $booking->lead_instructor_id = $waiting->id;
        $booking->save();

        $this->actingAs($manager);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('confirmBooking')
            ->assertSchemaComponentExists('lead_instructor_id', checkComponentUsing: function (Select $select) use ($accepted, $alsoAccepted): bool {
                return $select->getOptions() === [
                    __('Adult') => [
                        $accepted->getKey() => $accepted->name,
                        $alsoAccepted->getKey() => $alsoAccepted->name,
                    ],
                ];
            });
    }

    public function test_lead_options_group_adults_before_under_18_attendees(): void
    {
        $manager = User::factory()->teamManager()->create();
        $adult = User::factory()->create(['name' => 'Adult Lead']);
        $youngPerson = User::factory()->underEighteen()->create(['name' => 'Young Lead']);
        $booking = Booking::factory()->create();
        $this->attend($booking, $adult, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $youngPerson, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        $this->assertSame([
            __('Adult') => [$adult->getKey() => 'Adult Lead'],
            __('Under 18') => [$youngPerson->getKey() => 'Young Lead'],
        ], $booking->fresh()->leadOptions());
    }

    public function test_confirming_with_an_accepted_lead_succeeds(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create();
        $lead = User::factory()->create();
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionEnabled('confirmBooking')
            ->callAction('confirmBooking', data: [
                'lead_instructor_id' => $lead->id,
            ]);

        $booking->refresh();

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($lead->id, $booking->lead_instructor_id);
    }

    public function test_cancelling_requires_a_reason_and_notifies_everyone_who_has_not_declined(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Alex Leader']);
        $booking = Booking::factory()->create();
        $waiting = User::factory()->create();
        $declined = User::factory()->create();
        $this->attend($booking, $waiting, BookingAttendeeStatus::NeedsAction);
        $this->attend($booking, $declined, BookingAttendeeStatus::Declined);

        $this->actingAs($manager);

        try {
            app(UpdateBookingStatusAction::class)->cancel($booking, $manager, '   ');
            $this->fail('A blank reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reason', $exception->errors());
        }

        $this->assertSame(BookingStatus::Tentative, $booking->fresh()->status);

        Queue::fake();
        Mail::fake();

        Livewire::test(ListBookings::class)
            ->callTableAction('cancelBooking', $booking, data: [
                'reason' => 'Weather',
            ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertDatabaseHas('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $waiting->id,
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $activity = ActivityLog::query()
            ->where('subject_id', $booking->id)
            ->where('description', 'Weather')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('tentative', $activity->attribute_changes['old']['status'] ?? null);
        $this->assertSame('cancelled', $activity->attribute_changes['attributes']['status'] ?? null);

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingCancelledMail::class, function (BookingCancelledMail $mail) use ($waiting): bool {
            return $mail->hasTo($waiting->email) && str_contains($mail->render(), 'Weather');
        });
        Mail::assertNotSent(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo($declined->email));

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Weather')
            ->assertSee('Alex Leader');
    }

    public function test_restoring_asks_everyone_except_people_who_declined(): void
    {
        $manager = User::factory()->teamManager()->create();
        $lead = User::factory()->create();
        $booking = Booking::factory()->cancelled()->create([
            'lead_instructor_id' => $lead->id,
        ]);
        $accepted = User::factory()->create();
        $maybe = User::factory()->create();
        $declined = User::factory()->create();
        $this->attend($booking, $accepted, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $maybe, BookingAttendeeStatus::Tentative);
        $this->attend($booking, $declined, BookingAttendeeStatus::Declined);

        Queue::fake();
        Mail::fake();

        app(UpdateBookingStatusAction::class)->restore($booking, $manager);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame($lead->id, $booking->lead_instructor_id);
        $this->assertSame(BookingAttendeeStatus::NeedsAction, $booking->attendees()->find($accepted)->attendance->status);
        $this->assertSame(BookingAttendeeStatus::NeedsAction, $booking->attendees()->find($maybe)->attendance->status);
        $this->assertSame(BookingAttendeeStatus::Declined, $booking->attendees()->find($declined)->attendance->status);

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingChangedMail::class, 2);
        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail) use ($accepted): bool {
            $html = $mail->render();

            return $mail->hasTo($accepted->email)
                && str_contains($html, 'Yes')
                && str_contains($html, 'Maybe')
                && str_contains($html, 'No')
                && ! str_contains($html, 'You said you can help.');
        });
        Mail::assertNotSent(BookingChangedMail::class, fn (BookingChangedMail $mail): bool => $mail->hasTo($declined->email));
        Mail::assertNotSent(BookingInviteMail::class);
    }

    public function test_a_lead_who_steps_away_returns_a_confirmed_booking_to_tentative(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $lead = User::factory()->create();
        $booking->lead_instructor_id = $lead->id;
        $booking->save();
        $token = BookingAttendance::generateToken();
        $booking->attendees()->attach($lead->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => $token,
        ]);

        Livewire::withQueryParams(['invite' => $token, 'choice' => 'declined'])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $lead,
            ])
            ->assertSee('You are the lead instructor.')
            ->assertSee('If you cannot attend, another lead instructor will need to be found.')
            ->call('respond', 'declined');

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertNull($booking->lead_instructor_id);
        $this->assertSame(BookingAttendeeStatus::Declined, $booking->attendees()->find($lead)->attendance->status);

        $booking = Booking::factory()->confirmed()->create();
        $lead = User::factory()->create();
        $booking->lead_instructor_id = $lead->id;
        $booking->save();
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        (new RespondToBookingAction($booking))($lead, BookingAttendeeStatus::Tentative);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertNull($booking->lead_instructor_id);
    }

    public function test_a_lead_who_steps_away_alerts_team_managers(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $unverified = User::factory()->unverified()->create();
        $inactive = User::factory()->inactive()->create();
        $suspended = User::factory()->create(['suspended' => true]);
        $team = $manager->managedTeams()->first();
        $team->grant($unverified, MembershipRole::Manager);
        $team->grant($inactive, MembershipRole::Manager);
        $team->grant($suspended, MembershipRole::Manager);

        $lead = User::factory()->create(['name' => 'Casey Lead']);
        $attendee = User::factory()->create();
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $lead->id,
        ]);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $attendee, BookingAttendeeStatus::Accepted);

        (new RespondToBookingAction($booking))($lead, BookingAttendeeStatus::Declined);

        $reference = BookingMailThread::reference($booking->fresh());

        $this->assertTrue($manager->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($unverified->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($inactive->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($suspended->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($attendee->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($lead->notifications()->where('type', LeadNeededNotification::class)->exists());

        $mail = new LeadNeededMail($booking->fresh(), $lead);

        $this->assertStringContainsString('Lead needed:', $mail->envelope()->subject);
        $this->assertStringContainsString('Casey Lead', $mail->render());
        $this->assertStringContainsString($reference, $mail->headers()->referencesString() ?? '');
        Mail::assertNothingQueued();
    }

    public function test_removing_the_lead_uses_the_update_mail_for_managers_on_the_booking(): void
    {
        Queue::fake();
        Mail::fake();

        $actor = User::factory()->teamManager()->create();
        $team = $actor->managedTeams()->first();
        $otherManager = User::factory()->create();
        $attendeeManager = User::factory()->create();
        $team->grant($otherManager, MembershipRole::Manager);
        $team->grant($attendeeManager, MembershipRole::Manager);

        $lead = User::factory()->create();
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $lead->id,
        ]);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $actor, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $attendeeManager, BookingAttendeeStatus::Accepted);

        app(UpdateBookingStatusAction::class)->changeLead($booking, $actor, null);

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        $this->assertTrue($otherManager->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($actor->notifications()->where('type', LeadNeededNotification::class)->exists());
        $this->assertFalse($attendeeManager->notifications()->where('type', LeadNeededNotification::class)->exists());
        Mail::assertNotQueued(LeadNeededMail::class);

        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail) use ($actor): bool {
            $html = $mail->render();

            return $mail->hasTo($actor->email)
                && str_contains($html, 'Booking updated')
                && str_contains($html, 'Lead instructor needed')
                && str_contains($html, 'This booking has no lead instructor.')
                && str_contains($html, 'border-left: 4px solid #d97706');
        });
        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail) use ($attendeeManager): bool {
            return $mail->hasTo($attendeeManager->email)
                && str_contains($mail->render(), 'Lead instructor needed');
        });
        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail) use ($lead): bool {
            $html = $mail->render();

            return $mail->hasTo($lead->email)
                && str_contains($html, 'This booking has been updated')
                && str_contains($html, 'background-color: #fafafa')
                && str_contains($html, '>none</em>')
                && str_contains($html, '(changed)')
                && ! str_contains($html, 'Lead instructor needed')
                && ! str_contains($html, 'border-left: 4px solid #d97706');
        });
    }

    public function test_a_comment_from_the_lead_does_not_change_a_confirmed_booking(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $lead = User::factory()->create();
        $booking->lead_instructor_id = $lead->id;
        $booking->save();
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        (new RespondToBookingAction($booking))($lead, BookingAttendeeStatus::Accepted, 'Bringing ropes');

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($lead->id, $booking->lead_instructor_id);
    }

    public function test_changing_or_removing_the_lead_follows_who_is_attending(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->confirmed()->create();
        $attending = User::factory()->create(['name' => 'Casey Lead']);
        $maybe = User::factory()->create(['name' => 'Maybe Person']);
        $this->attend($booking, $attending, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $maybe, BookingAttendeeStatus::Tentative);
        $booking->lead_instructor_id = $attending->id;
        $booking->save();

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Casey Lead');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('changeLead')
            ->assertSee('Casey Lead')
            ->assertDontSee('Maybe Person')
            ->callMountedAction();

        try {
            app(UpdateBookingStatusAction::class)->changeLead($booking->fresh(), $manager, $maybe->id);
            $this->fail('A lead who is not attending was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lead_instructor_id', $exception->errors());
        }

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame($attending->id, $booking->lead_instructor_id);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('changeLead', data: [
                'lead_instructor_id' => 'remove',
            ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertNull($booking->lead_instructor_id);
    }

    public function test_delete_is_only_available_for_a_cancelled_booking(): void
    {
        $manager = User::factory()->teamManager()->create();
        $tentative = Booking::factory()->create();
        $cancelled = Booking::factory()->cancelled()->create();

        $this->actingAs($manager);

        $this->assertFalse($manager->can('delete', $tentative));
        $this->assertTrue($manager->can('delete', $cancelled));

        Livewire::test(EditBooking::class, ['record' => $tentative->getRouteKey()])
            ->assertActionHidden('delete');

        Livewire::test(ViewBooking::class, ['record' => $cancelled->getRouteKey()])
            ->assertActionVisible('delete');
    }

    public function test_view_status_actions_match_the_booking_state(): void
    {
        $manager = User::factory()->teamManager()->create();
        $tentative = Booking::factory()->create();
        $confirmed = Booking::factory()->confirmed()->create();
        $cancelled = Booking::factory()->cancelled()->create();

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $tentative->getRouteKey()])
            ->assertSee('Tentative booking')
            ->assertActionVisible('confirmBooking')
            ->assertActionVisible('cancelBooking')
            ->assertActionVisible('edit');

        Livewire::test(ViewBooking::class, ['record' => $confirmed->getRouteKey()])
            ->assertSee('Confirmed booking')
            ->assertActionVisible('cancelBooking')
            ->assertActionVisible('returnToTentative')
            ->assertActionVisible('edit');

        Livewire::test(ViewBooking::class, ['record' => $cancelled->getRouteKey()])
            ->assertSee('Cancelled booking')
            ->assertActionHidden('edit')
            ->assertActionVisible('restoreBooking')
            ->assertActionVisible('delete');
    }

    public function test_a_cancelled_booking_cannot_be_edited_or_quietly_emailed(): void
    {
        $manager = User::factory()->teamManager()->create();
        $tentative = Booking::factory()->create(['group_name' => 'Still open']);
        $cancelled = Booking::factory()->cancelled()->create(['group_name' => 'Already cancelled']);
        $attendee = User::factory()->create();
        $this->attend($cancelled, $attendee, BookingAttendeeStatus::NeedsAction);
        $this->attend($tentative, $manager, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($manager);

        $tentativeEditUrl = BookingResource::getUrl('edit', ['record' => $tentative], panel: 'teams');
        $cancelledEditUrl = BookingResource::getUrl('edit', ['record' => $cancelled], panel: 'teams');

        Livewire::test(ListBookings::class)
            ->assertSee($tentativeEditUrl)
            ->assertDontSee($cancelledEditUrl);

        $cancelledView = Livewire::test(ViewBooking::class, ['record' => $cancelled->getRouteKey()])
            ->assertActionHidden('edit')
            ->assertActionVisible('restoreBooking')
            ->assertActionVisible('delete');

        $this->assertSame(
            [__('Manage')],
            $this->visibleHeaderLabels($cancelledView->instance()),
        );

        $tentativeView = Livewire::test(ViewBooking::class, ['record' => $tentative->getRouteKey()])
            ->assertActionVisible('edit');

        $this->assertSame(
            [__('Respond'), __('Manage')],
            $this->visibleHeaderLabels($tentativeView->instance()),
        );

        $this->assertFalse(BookingResource::canEdit($cancelled->fresh()));

        Livewire::test(EditBooking::class, ['record' => $cancelled->getRouteKey()])
            ->assertStatus(403);

        $component = Livewire::test(EditBooking::class, ['record' => $tentative->getRouteKey()])
            ->fillForm(['group_name' => 'Sneaky change']);

        $tentative->status = BookingStatus::Cancelled;
        $tentative->save();

        $component->call('save')->assertStatus(403);

        $this->assertSame('Still open', $tentative->fresh()->group_name);

        $cancelled->group_name = 'Quiet change';
        $cancelled->save();

        Queue::fake();
        Mail::fake();

        app(UpdateBookingStatusAction::class)($cancelled, $manager);

        $this->assertCount(0, Queue::pushed(SendBookingNotification::class));
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_restoring_sends_one_email_that_includes_changes_made_then(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->cancelled()->create(['group_name' => 'Old Group']);
        $attendee = User::factory()->create();
        $this->attend($booking, $attendee, BookingAttendeeStatus::Accepted);
        $timezone = $booking->timezone instanceof \DateTimeZone
            ? $booking->timezone->getName()
            : (string) $booking->timezone;

        $this->actingAs($manager);

        Queue::fake();
        Mail::fake();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('restoreBooking', data: [
                'activity_id' => $booking->activity_id,
                'audience_id' => $booking->audience_id,
                'venue_id' => $booking->venue_id,
                'start_date' => $booking->start_at->timezone($timezone)->toDateString(),
                'start_time' => $booking->start_at->timezone($timezone)->format('H:i'),
                'end_date' => $booking->end_at->timezone($timezone)->toDateString(),
                'end_time' => $booking->end_at->timezone($timezone)->format('H:i'),
                'timezone' => $timezone,
                'group_name' => 'New Group',
                'notes' => $booking->notes,
                'lead_instructor_notes' => $booking->lead_instructor_notes,
            ]);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame('New Group', $booking->group_name);

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail) use ($attendee): bool {
            return $mail->hasTo($attendee->email)
                && str_contains($mail->render(), 'Group');
        });
        Mail::assertSent(BookingChangedMail::class, 1);
    }

    /**
     * @return list<string>
     */
    private function visibleHeaderLabels(object $page): array
    {
        return collect($page->getCachedHeaderActions())
            ->filter(fn (Action|ActionGroup $action): bool => $action->isVisible())
            ->map(fn (Action|ActionGroup $action): string => (string) $action->getLabel())
            ->values()
            ->all();
    }

    private function attend(Booking $booking, User $user, BookingAttendeeStatus $status): void
    {
        $booking->attendees()->attach($user->id, [
            'status' => $status->value,
            'token' => BookingAttendance::generateToken(),
        ]);
    }
}
