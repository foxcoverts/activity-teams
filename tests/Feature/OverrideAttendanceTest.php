<?php

namespace Tests\Feature;

use App\Actions\AddAttendeeAction;
use App\Actions\OverrideAttendanceAction;
use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Mail\AttendanceRecordedMail;
use App\Mail\BookingInviteMail;
use App\Mail\LeadNeededMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Support\BookingMailThread;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Tests\TestCase;

class OverrideAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_a_manager_can_record_attendance_from_the_roster(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create(['name' => 'Alex Leader']);
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $other = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::NeedsAction);
        $this->attend($booking, $other, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])
            ->assertTableActionVisible('editAttendance', $member)
            ->assertTableActionHasLabel('editAttendance', 'Edit', $member)
            ->mountTableAction('editAttendance', $member)
            ->assertSchemaComponentExists('user_id', checkComponentUsing: function (Select $select) use ($member): bool {
                return $select->isDisabled()
                    && $select->getOptions() === [$member->getKey() => $member->name];
            })
            ->assertSchemaComponentExists('response', checkComponentUsing: function (ToggleButtons $buttons): bool {
                return array_keys($buttons->getOptions()) === [
                    BookingAttendeeResponse::Yes->value,
                    BookingAttendeeResponse::No->value,
                    BookingAttendeeResponse::Maybe->value,
                ]
                    && $buttons->getIcon(BookingAttendeeResponse::Yes->value) === BookingAttendeeResponse::Yes->getIcon()
                    && $buttons->getColor(BookingAttendeeResponse::Maybe->value) === BookingAttendeeResponse::Maybe->getColor();
            })
            ->setTableActionData([
                'response' => BookingAttendeeResponse::Yes->value,
                'comment' => 'Said yes in person',
            ])
            ->callMountedTableAction();

        $this->assertSame(
            BookingAttendeeStatus::Accepted,
            $booking->attendees()->find($member)->attendance->status,
        );
        $this->assertSame('Said yes in person', $booking->attendees()->find($member)->attendance->comment);

        $activity = ActivityLog::query()->where('event', 'attendance_updated')->first();
        $this->assertNotNull($activity);
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame('Recorded Can help for Casey.', $activity->description);
        $this->assertSame('manager', $activity->properties['via']);
        $this->assertSame($member->id, $activity->properties['attendee_id']);

        $team = $manager->managedTeams()->first();

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('recorded')
            ->assertSee('Can help')
            ->assertSeeHtml('<strong>Can help</strong>')
            ->assertSee('Casey')
            ->assertSee('Alex Leader')
            ->assertSeeHtml(MemberResource::getUrl('view', ['record' => $manager], panel: 'manage', tenant: $team))
            ->assertSeeHtml(MemberResource::getUrl('view', ['record' => $member], panel: 'manage', tenant: $team));

        Mail::assertSent(AttendanceRecordedMail::class, function (AttendanceRecordedMail $mail) use ($booking, $member): bool {
            $html = $mail->render();
            $recorded = strpos($html, 'A manager recorded your attendance as Can help.');

            return $mail->hasTo($member->email)
                && $mail->envelope()->subject === BookingMailThread::subject($booking, 'Attendance')
                && $recorded !== false
                && str_contains($html, 'Said yes in person')
                && strpos($html, $booking->activity->name) < $recorded
                && $recorded < strpos($html, 'View response');
        });
        Mail::assertNotSent(AttendanceRecordedMail::class, fn (AttendanceRecordedMail $mail): bool => $mail->hasTo($other->email));
        Mail::assertSentCount(1);
    }

    public function test_a_member_cannot_edit_attendance_from_the_roster_and_their_own_answer_is_not_emailed(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($member);

        $attendance = $booking->attendees()->find($member)->attendance;
        $this->assertFalse($member->can('override', $attendance));

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])->assertStatus(403);

        (new RespondToBookingAction($booking))($member, BookingAttendeeStatus::Accepted);

        Mail::assertNothingSent();
    }

    public function test_a_maybe_recorded_by_a_manager_asks_for_a_definite_answer(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::NeedsAction);

        app(OverrideAttendanceAction::class)($booking, $member, $manager, BookingAttendeeStatus::Tentative);

        Mail::assertSent(AttendanceRecordedMail::class, function (AttendanceRecordedMail $mail) use ($booking, $member): bool {
            $html = $mail->render();
            $token = $booking->attendees()->find($member)->attendance->token;

            return $mail->hasTo($member->email)
                && str_contains($html, 'A manager recorded your attendance as Maybe.')
                && ! str_contains($html, 'Please update your response.')
                && str_contains($html, 'Can you attend this event?')
                && str_contains($html, e(route('respond', [$booking, $member, 'invite' => $token, 'choice' => 'accepted'])))
                && str_contains($html, e(route('respond', [$booking, $member, 'invite' => $token, 'choice' => 'tentative'])))
                && str_contains($html, e(route('respond', [$booking, $member, 'invite' => $token, 'choice' => 'declined'])))
                && ! str_contains($html, 'View response');
        });
    }

    public function test_a_past_booking_can_be_corrected_without_email_and_keeps_its_status(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
            'lead_instructor_id' => $lead->id,
        ]);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])->assertTableActionVisible('editAttendance', $lead);

        app(OverrideAttendanceAction::class)($booking, $lead, $manager, BookingAttendeeStatus::Declined);

        $booking->refresh();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->lead_instructor_id);
        $this->assertSame(BookingAttendeeStatus::Declined, $booking->attendees()->find($lead)->attendance->status);
        Mail::assertNothingSent();
    }

    public function test_recording_that_an_upcoming_lead_cannot_attend_returns_the_booking_to_tentative(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $otherManager = User::factory()->create();
        $manager->managedTeams()->first()->grant($otherManager, MembershipRole::Manager);
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'lead_instructor_id' => $lead->id,
        ]);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        app(OverrideAttendanceAction::class)($booking, $lead, $manager, BookingAttendeeStatus::Tentative);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertNull($booking->lead_instructor_id);
        Mail::assertSent(AttendanceRecordedMail::class, fn (AttendanceRecordedMail $mail): bool => $mail->hasTo($lead->email));
        Mail::assertSent(LeadNeededMail::class, fn (LeadNeededMail $mail): bool => $mail->hasTo($otherManager->email));
        Mail::assertNotSent(LeadNeededMail::class, fn (LeadNeededMail $mail): bool => $mail->hasTo($manager->email));
        Mail::assertSentCount(2);
    }

    public function test_a_cancelled_booking_refuses_an_attendance_edit(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->cancelled()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::NeedsAction);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])->assertTableActionHidden('editAttendance', $member);

        try {
            app(OverrideAttendanceAction::class)($booking, $member, $manager, BookingAttendeeStatus::Accepted);
            $this->fail('Attendance on a cancelled booking was saved.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(
            BookingAttendeeStatus::NeedsAction,
            $booking->attendees()->find($member)->attendance->status,
        );
    }

    public function test_a_manager_can_add_someone_with_a_known_response(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $already = User::factory()->teamMember()->create(['name' => 'Already There']);
        $member = User::factory()->teamMember()->create(['name' => 'Casey']);
        $booking = Booking::factory()->create();
        $this->attend($booking, $already, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])
            ->assertTableActionVisible('invite')
            ->assertTableActionVisible('add')
            ->assertTableActionHasLabel('add', 'Add')
            ->mountTableAction('add')
            ->assertSchemaComponentExists('user_id', checkComponentUsing: function (Select $select) use ($already, $member): bool {
                $options = $select->getOptions();

                return array_key_exists($member->getKey(), $options)
                    && ! array_key_exists($already->getKey(), $options);
            })
            ->setTableActionData([
                'user_id' => $member->getKey(),
                'response' => BookingAttendeeResponse::Yes,
                'comment' => 'Said yes in person',
            ])
            ->callMountedTableAction();

        $attendance = $booking->attendees()->find($member)->attendance;
        $this->assertSame(BookingAttendeeStatus::Accepted, $attendance->status);
        $this->assertSame('Said yes in person', $attendance->comment);

        $activity = ActivityLog::query()->where('event', 'attendance_updated')->first();
        $this->assertSame('Recorded Can help for Casey.', $activity->description);
        $this->assertSame('manager', $activity->properties['via']);

        Mail::assertSent(AttendanceRecordedMail::class, fn (AttendanceRecordedMail $mail): bool => $mail->hasTo($member->email));
        Mail::assertNotSent(BookingInviteMail::class);
        Mail::assertSentCount(1);
    }

    public function test_a_past_booking_can_gain_an_attendee_without_email(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
        ]);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])
            ->assertTableActionHidden('invite')
            ->assertTableActionVisible('add')
            ->callTableAction('add', data: [
                'user_id' => $member->getKey(),
                'response' => BookingAttendeeResponse::No->value,
                'comment' => 'Did not attend',
            ]);

        $this->assertSame(BookingAttendeeStatus::Declined, $booking->attendees()->find($member)->attendance->status);
        Mail::assertNothingSent();
    }

    public function test_a_cancelled_booking_refuses_adding_an_attendee(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->cancelled()->create();

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])->assertTableActionHidden('add');

        try {
            app(AddAttendeeAction::class)($booking, $member, $manager, BookingAttendeeStatus::Accepted);
            $this->fail('An attendee was added to a cancelled booking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_id', $exception->errors());
        }

        $this->assertNull($booking->attendees()->find($member));
    }

    private function attend(Booking $booking, User $user, BookingAttendeeStatus $status): void
    {
        $booking->attendees()->attach($user->id, [
            'status' => $status->value,
            'token' => BookingAttendance::generateToken(),
        ]);
    }
}
