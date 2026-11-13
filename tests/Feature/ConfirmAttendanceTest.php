<?php

namespace Tests\Feature;

use App\Actions\ConfirmAttendanceAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\PastBookingAttendeeStatus;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Mail\BookingChangedMail;
use App\Mail\BookingIcsRefreshMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Notifications\AttendanceRecordedNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ConfirmAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_past_booking_attendance_uses_past_tense_labels(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
        ]);
        $member = User::factory()->teamMember()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $attendance = $booking->attendees()->find($member)->attendance;

        $this->assertInstanceOf(PastBookingAttendeeStatus::class, $attendance->status);
        $this->assertSame(BookingAttendeeStatus::Accepted->value, $attendance->status->value);
        $this->assertTrue($attendance->status->isSame(BookingAttendeeStatus::Accepted));
        $this->assertSame(__('Helped'), $attendance->status->getLabel());
        $this->assertSame(__('You said you helped.'), $attendance->status->responseSentence());
        $this->assertTrue($attendance->isAccepted());
    }

    public function test_future_booking_attendance_keeps_future_labels(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $member = User::factory()->teamMember()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $attendance = $booking->attendees()->find($member)->attendance;

        $this->assertInstanceOf(BookingAttendeeStatus::class, $attendance->status);
        $this->assertSame(__('Can help'), $attendance->status->getLabel());
    }

    public function test_past_and_future_attendee_status_share_the_same_instance_api(): void
    {
        $this->assertSame(
            $this->publicInstanceMethods(BookingAttendeeStatus::class),
            $this->publicInstanceMethods(PastBookingAttendeeStatus::class),
        );
    }

    public function test_booking_status_mail_renders_past_tense_attendance_copy(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
        ]);
        $member = User::factory()->teamMember()->create();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $mail = new BookingChangedMail($booking, $member, changes: ['notes']);

        $this->assertInstanceOf(PastBookingAttendeeStatus::class, $mail->attendance?->status);
        $html = $mail->render();

        $this->assertStringContainsString(__('You said you helped.'), $html);
        $this->assertStringNotContainsString(__('You said you can help.'), $html);
    }

    public function test_confirm_attendees_action_is_hidden_before_session_day(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->addDays(2)->setTime(9, 30),
            'end_at' => now()->addDays(2)->setTime(11, 30),
        ]);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('confirmAttendees');
    }

    public function test_blank_mark_leaves_unconfirmed_accepted_in_needs_confirming(): void
    {
        Mail::fake();
        Notification::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('confirmAttendees')
            ->callAction('confirmAttendees', data: [
                'needs_confirming' => [[
                    'user_id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'mark' => null,
                ]],
                'confirmed' => [],
            ]);

        $attendance = $booking->fresh()->attendees()->find($member)->attendance;
        $this->assertTrue($attendance->isAccepted());
        $this->assertNull($attendance->attendance_confirmed_at);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_confirm_accepted_moves_person_to_confirmed_section(): void
    {
        Mail::fake();
        Notification::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'timezone' => 'Europe/London',
        ]);
        $this->attend($booking, $member, BookingAttendeeStatus::Tentative);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('confirmAttendees', data: [
                'needs_confirming' => [[
                    'user_id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'mark' => ConfirmAttendanceAction::MARK_CONFIRM,
                ]],
                'confirmed' => [],
            ]);

        $attendance = $booking->fresh()->attendees()->find($member)->attendance;
        $this->assertTrue($attendance->isAccepted());
        $this->assertNotNull($attendance->attendance_confirmed_at);

        $form = Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('confirmAttendees');
        $confirmedRows = collect($form->get('mountedActions.0.data.confirmed') ?? [])->values();

        $this->assertSame([], $form->get('mountedActions.0.data.needs_confirming') ?? []);
        $this->assertCount(1, $confirmedRows);
        $this->assertSame((string) $member->getKey(), $confirmedRows[0]['user_id']);
        $this->assertSame(ConfirmAttendanceAction::MARK_CONFIRM, $confirmedRows[0]['mark']);

        Notification::assertNotSentTo($member, AttendanceRecordedNotification::class);
        Mail::assertQueued(BookingIcsRefreshMail::class, fn (BookingIcsRefreshMail $mail): bool => $mail->hasTo($member->email));
    }

    public function test_confirm_declined_removes_person_from_rollcall_list(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $accepted = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $this->attend($booking, $accepted, BookingAttendeeStatus::Accepted);

        $this->actingAs($manager);

        app(ConfirmAttendanceAction::class)($booking, $manager, [
            (string) $accepted->getKey() => ConfirmAttendanceAction::MARK_DECLINE,
        ]);

        $attendance = $booking->fresh()->attendees()->find($accepted)->attendance;
        $this->assertTrue($attendance->isDeclined());
        $this->assertNull($attendance->attendance_confirmed_at);

        $form = Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('confirmAttendees');

        $this->assertSame([], $form->get('mountedActions.0.data.needs_confirming') ?? []);
        $this->assertSame([], $form->get('mountedActions.0.data.confirmed') ?? []);
        Mail::assertQueued(BookingIcsRefreshMail::class);
    }

    public function test_lead_can_confirm_attendees(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('confirmAttendees')
            ->callAction('confirmAttendees', data: [
                'needs_confirming' => [[
                    'user_id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'mark' => ConfirmAttendanceAction::MARK_CONFIRM,
                ]],
                'confirmed' => [],
            ]);

        $this->assertNotNull($booking->fresh()->attendees()->find($member)->attendance->attendance_confirmed_at);
    }

    private function attend(Booking $booking, User $user, BookingAttendeeStatus $status): void
    {
        $booking->attendees()->attach($user->id, [
            'status' => $status->value,
            'token' => BookingAttendance::generateToken(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function publicInstanceMethods(string $enum): array
    {
        return collect((new ReflectionClass($enum))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === $enum
                && ! $method->isStatic())
            ->map(fn (ReflectionMethod $method): string => $method->getName())
            ->sort()
            ->values()
            ->all();
    }
}
