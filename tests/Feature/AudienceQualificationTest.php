<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Teams\Resources\Activities\Pages\ListActivities;
use App\Mail\BookingInviteMail;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AudienceQualificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_pairing_accepts_any_lead_and_a_missing_pairing_cannot_be_saved(): void
    {
        $lead = User::factory()->teamMember()->create();
        $activity = Activity::factory()->create();
        $empty = ActivityAudience::factory()->for($activity)->create();
        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $empty->audience_id,
            'lead_instructor_id' => $lead->getKey(),
        ]);

        $this->assertFalse($booking->requiresQualification());
        $this->assertTrue($booking->metBy($lead));

        $booking->audience_id = Audience::factory()->create()->getKey();

        try {
            $booking->save();
            $this->fail('A booking saved an audience the activity does not offer.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('audience_id', $exception->errors());
        }
    }

    public function test_scout_rows_reject_a_mountain_training_only_member(): void
    {
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $scouts = Audience::factory()->create(['name' => 'Scouts']);
        $guides = Audience::factory()->create(['name' => 'Guides']);
        $scoutPairing = ActivityAudience::factory()->for($activity)->for($scouts)->create();
        $guidePairing = ActivityAudience::factory()->for($activity)->for($guides)->create();
        $scoutPairing->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
        ]);
        $guidePairing->qualificationRequirements()->create([
            'kind' => 'girlguiding',
        ]);
        $guidePairing->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);

        $instructor = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($instructor)->mountainTraining('Climbing Wall Instructor')->create();

        $scoutBooking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $scouts->getKey(),
        ]);
        $guideBooking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $guides->getKey(),
        ]);

        $this->assertFalse($scoutBooking->metBy($instructor));
        $this->assertTrue($guideBooking->metBy($instructor));
    }

    public function test_two_audiences_at_one_venue_share_the_activity_key(): void
    {
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $scouts = Audience::factory()->create();
        $team = Audience::factory()->create();
        ActivityAudience::factory()->for($activity)->for($scouts)->create();
        ActivityAudience::factory()->for($activity)->for($team)->create();
        $key = Lock::factory()->create(['name' => 'Wall key']);
        $key->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);

        $scoutBooking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $scouts->getKey(),
            'venue_id' => $venue->getKey(),
        ]);
        $teamBooking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $team->getKey(),
            'venue_id' => $venue->getKey(),
            'group_name' => null,
        ]);

        $this->assertSame(
            [$key->getKey()],
            $scoutBooking->requiredLocks()->modelKeys(),
        );
        $this->assertSame(
            $scoutBooking->requiredLocks()->modelKeys(),
            $teamBooking->requiredLocks()->modelKeys(),
        );
        $this->assertNull($teamBooking->group_name);
        $this->assertSame($activity->name.' — '.$team->name, $teamBooking->icsSummary());
        $this->assertStringNotContainsString(
            __('Group'),
            (new BookingInviteMail($teamBooking, User::factory()->create()))->render(),
        );
    }

    public function test_changing_audience_away_from_the_leads_qualification_is_refused(): void
    {
        $activity = Activity::factory()->create();
        $scouts = Audience::factory()->create();
        $public = Audience::factory()->create();
        $scoutPairing = ActivityAudience::factory()->for($activity)->for($scouts)->create();
        $publicPairing = ActivityAudience::factory()->for($activity)->for($public)->create();
        $scoutPairing->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
        ]);
        $publicPairing->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $lead = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($lead)->scoutPermit()->create([
            'expires_on' => now()->addYear()->toDateString(),
        ]);
        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $scouts->getKey(),
            'lead_instructor_id' => $lead->getKey(),
        ]);

        $booking->audience_id = $public->getKey();

        try {
            $booking->save();
            $this->fail('The lead stayed assigned for an audience they do not meet.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('audience_id', $exception->errors());
        }

        $booking->audience_id = $scouts->getKey();
        $empty = ActivityAudience::factory()->for($activity)->create();
        $booking->audience_id = $empty->audience_id;
        $booking->save();

        $this->assertTrue($booking->fresh()->audience->is($empty->audience));
    }

    public function test_a_pairing_used_by_a_booking_cannot_be_deleted(): void
    {
        $activity = Activity::factory()->create();
        $pairing = ActivityAudience::factory()->for($activity)->create();
        Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $pairing->audience_id,
        ]);

        try {
            $pairing->delete();
            $this->fail('A pairing used by a booking was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('audience_id', $exception->errors());
        }

        $this->assertModelExists($pairing);

        try {
            $pairing->audience->delete();
            $this->fail('An audience used by a booking was deleted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }
    }

    public function test_a_new_booking_uses_the_team_activity_default_audience(): void
    {
        Filament::setCurrentPanel('manage');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $climbing = Activity::factory()->create(['name' => 'Climbing']);
        $archery = Activity::factory()->create(['name' => 'Archery']);
        $scouts = Audience::factory()->create(['name' => 'Scouts']);
        $guides = Audience::factory()->create(['name' => 'Guides']);
        $public = Audience::factory()->create(['name' => 'Public']);
        ActivityAudience::factory()->for($climbing)->for($scouts)->create();
        ActivityAudience::factory()->for($climbing)->for($guides)->create();
        ActivityAudience::factory()->for($archery)->for($guides)->create();
        ActivityAudience::factory()->for($archery)->for($public)->create();
        $team->setDefaultActivity($climbing);
        $team->setDefaultAudience($climbing, $scouts);
        $team->setDefaultAudience($archery, $public);
        Venue::factory()->create();

        $this->actingAs($manager);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'activity_id' => $climbing->getKey(),
                'audience_id' => $scouts->getKey(),
            ])
            ->set('data.audience_id', $guides->getKey())
            ->set('data.activity_id', $archery->getKey())
            ->assertFormSet([
                'audience_id' => $guides->getKey(),
            ]);

        $maintenance = Activity::factory()->create(['name' => 'Maintenance']);
        ActivityAudience::factory()->for($maintenance)->for($public)->create();
        $team->setDefaultAudience($maintenance, $public);

        Livewire::test(CreateBooking::class)
            ->set('data.activity_id', $archery->getKey())
            ->set('data.audience_id', $guides->getKey())
            ->set('data.activity_id', $maintenance->getKey())
            ->assertFormSet([
                'audience_id' => $public->getKey(),
            ]);

        Filament::setCurrentPanel('teams');

        Livewire::test(ListActivities::class)
            ->assertSee('Scouts')
            ->callTableAction('setDefaultAudience', $climbing, data: [
                'default_audience_id' => null,
            ]);

        $this->assertNull($team->fresh()->defaultAudienceFor($climbing));
    }

    public function test_the_logged_out_summary_does_not_show_the_audience(): void
    {
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $audience = Audience::factory()->create(['name' => 'Guides Only Audience']);
        ActivityAudience::factory()->for($activity)->for($audience)->create();
        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $audience->getKey(),
            'group_name' => 'Secret Group Name',
        ]);

        $this->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee('Climbing')
            ->assertDontSee('Guides Only Audience')
            ->assertDontSee('Secret Group Name');
    }
}
