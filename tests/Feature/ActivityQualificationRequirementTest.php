<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Filament\Admin\Resources\Activities\Pages\ViewActivity;
use App\Filament\Admin\Resources\Activities\RelationManagers\ActivityAudiencesRelationManager;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Qualification;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityQualificationRequirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_an_activity_with_no_rows_accepts_a_member_with_no_qualifications(): void
    {
        $activity = Activity::factory()->create();
        $pairing = ActivityAudience::factory()->for($activity)->create();
        $member = User::factory()->teamMember()->create();

        $this->assertFalse($pairing->requiresQualification());
        $this->assertSame(0, $member->qualifications()->count());
        $this->assertTrue($pairing->metBy($member));
    }

    public function test_any_current_qualification_that_covers_a_row_is_enough(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'girlguiding',
        ]);

        $member = User::factory()->teamMember()->create();
        $this->assertFalse($this->pairing($activity)->metBy($member));

        Qualification::factory()->forMember($member)->mountainTraining('Rock Climbing Instructor')->create();

        $this->assertTrue($this->pairing($activity)->metBy($member->fresh()));

        $other = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($other)->mountainTraining('Lowland Leader')->create();

        $this->assertFalse($this->pairing($activity)->metBy($other));

        $guide = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($guide)->girlguiding('2')->create();

        $this->assertTrue($this->pairing($activity)->metBy($guide));
    }

    public function test_a_scout_permit_matches_on_activity_category_and_type_and_ignores_restrictions(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'category' => 'Artificial Top Rope',
                'permit_type' => 'leadership',
            ],
        ]);

        $member = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($member)->scoutPermit([
            'category' => 'Natural Lead Climbing',
            'permit_type' => 'supervisory',
            'restrictions' => 'No night climbing',
        ])->create();

        $this->assertTrue($this->pairing($activity)->metBy($member));

        $personal = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($personal)->scoutPermit([
            'permit_type' => 'personal',
            'restrictions' => 'Unrestricted',
        ])->create();

        $this->assertFalse($this->pairing($activity)->metBy($personal));

        $rock = Activity::factory()->create();
        $this->pairing($rock)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'category' => 'Natural Top Rope',
                'permit_type' => 'leadership',
            ],
        ]);

        $tower = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($tower)->scoutPermit([
            'category' => 'Artificial Top Rope',
            'permit_type' => 'leadership',
        ])->create();

        $this->assertFalse($this->pairing($rock)->metBy($tower));

        $expired = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($expired)->scoutPermit([
            'permit_type' => 'leadership',
        ])->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($this->pairing($activity)->metBy($expired));
    }

    public function test_a_river_permit_does_not_meet_an_open_inland_requirement(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Canoeing Open Inland',
                'category' => 'B1',
            ],
        ]);

        $river = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($river)->scoutPermit([
            'activity' => 'Canoeing River',
            'category' => 'B2',
            'permit_type' => 'leadership',
        ])->create();

        $this->assertFalse($this->pairing($activity)->metBy($river));

        $inland = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($inland)->scoutPermit([
            'activity' => 'Canoeing Open Inland',
            'category' => 'B2',
            'permit_type' => 'leadership',
        ])->create();

        $this->assertTrue($this->pairing($activity)->metBy($inland));

        $sameRiver = Activity::factory()->create();
        $this->pairing($sameRiver)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Canoeing River',
                'category' => 'B1',
            ],
        ]);

        $this->assertTrue($this->pairing($sameRiver)->metBy($river));
    }

    public function test_a_permit_must_still_be_valid_on_the_booking_end_date(): void
    {
        $this->travelTo('2026-10-25 12:00:00');

        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
        ]);

        $member = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($member)->scoutPermit()->create([
            'expires_on' => '2026-11-01',
        ]);

        $this->assertTrue($this->pairing($activity)->metBy($member));

        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-11-02 09:30:00',
            'end_at' => '2026-11-02 16:00:00',
            'timezone' => 'Europe/London',
        ]);

        $this->assertFalse($this->pairing($activity)->metBy($member, $booking));

        $lastDay = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-11-01 09:30:00',
            'end_at' => '2026-11-01 16:00:00',
            'timezone' => 'Europe/London',
        ]);

        $this->assertTrue($this->pairing($activity)->metBy($member, $lastDay));

        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $lastDay->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->assertSame([], $booking->fresh()->leadOptions());
        $this->assertSame(
            [$member->getKey() => $member->name],
            $lastDay->fresh()->leadOptions(),
        );
    }

    public function test_an_nsra_requirement_uses_the_short_name_and_covers_lower_awards(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'nsra',
            'criteria' => ['award' => 'Club Instructor'],
        ]);

        $this->assertSame(
            'NSRA — Club Instructor',
            $this->pairing($activity)->qualificationRequirements()->first()?->summary,
        );

        $coach = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($coach)->nsra('Club Coach')->create();

        $this->assertTrue($this->pairing($activity)->metBy($coach));

        $tutor = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($tutor)->nsra('YPS Tutor')->create();

        $this->assertFalse($this->pairing($activity)->metBy($tutor));
    }

    public function test_an_archery_gb_requirement_does_not_treat_a_coach_as_an_instructor(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'archery_gb_awards',
            'criteria' => ['award' => 'Instructor Award'],
        ]);

        $this->assertSame(
            'Archery GB — Instructor Award',
            $this->pairing($activity)->qualificationRequirements()->first()?->summary,
        );

        $plus = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($plus)->archeryGb('Instructor Plus')->create();

        $this->assertTrue($this->pairing($activity)->metBy($plus));

        $coach = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($coach)->archeryGb('Session Coach')->create();

        $this->assertFalse($this->pairing($activity)->metBy($coach));
    }

    public function test_a_requirement_does_not_keep_a_category_without_a_group(): void
    {
        $activity = Activity::factory()->create();
        $this->pairing($activity)->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'category' => 'B1',
            ],
        ]);

        $this->assertSame([], $this->pairing($activity)->qualificationRequirements()->first()?->criteria);
    }

    public function test_a_system_admin_can_set_and_clear_alternatives(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $audience = Audience::factory()->create(['name' => 'Scouts']);

        $this->actingAs($manager);
        $this->get(ActivityResource::getUrl('create'))->assertForbidden();

        $this->actingAs($admin);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'name' => 'Scout climbing',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('name', 'Scout climbing')->first();
        $this->assertNotNull($activity);

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->callTableAction('create', data: [
            'audience_id' => $audience->getKey(),
            'qualificationRequirements' => [
                [
                    'kind' => 'scout_permit',
                    'group' => 'Climbing and Abseiling',
                    'permit_type' => 'leadership',
                ],
            ],
        ])->assertHasNoFormErrors();

        $pairing = $activity->activityAudiences()->first();
        $this->assertInstanceOf(ActivityAudience::class, $pairing);
        $this->assertTrue($pairing->requiresQualification());
        $this->assertSame(
            'Scout permit — Climbing and Abseiling — Leadership',
            $pairing->qualificationRequirements()->first()?->summary,
        );

        $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
            ->assertOk();

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => ViewActivity::class,
        ])->assertSee('Scout permit — Climbing and Abseiling — Leadership');

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->callTableAction('edit', $pairing, data: [
            'qualificationRequirements' => [],
        ])->assertHasNoFormErrors();

        $this->assertFalse($pairing->fresh()->requiresQualification());

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => ViewActivity::class,
        ])->assertSee('No qualification required.');
    }

    public function test_a_requirement_row_stores_one_scout_group(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $audience = Audience::factory()->create();
        $this->actingAs($admin);

        Livewire::test(CreateActivity::class)
            ->fillForm(['name' => 'Canoeing'])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('name', 'Canoeing')->first();
        $this->assertNotNull($activity);

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->callTableAction('create', data: [
            'audience_id' => $audience->getKey(),
            'qualificationRequirements' => [
                [
                    'kind' => 'scout_permit',
                    'group' => 'Canoeing Open Inland',
                    'node' => 'B2',
                ],
                [
                    'kind' => 'scout_permit',
                    'group' => 'Canoeing River',
                    'node' => 'B2',
                ],
            ],
        ])->assertHasNoFormErrors();

        $this->assertSame(2, $activity->activityAudiences()->first()?->qualificationRequirements()->count());
        $this->assertSame(
            ['Canoeing Open Inland', 'Canoeing River'],
            $activity->activityAudiences()->first()?->qualificationRequirements()->orderBy('sort')->get()->map(fn ($row) => $row->criteria['activity'])->all(),
        );
    }

    public function test_adding_qualifications_preselects_the_only_audience(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create();
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $this->actingAs($admin);

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->mountTableAction('create')
            ->assertSet('mountedActions.0.data.audience_id', $audience->getKey());

        Audience::factory()->create(['name' => 'Guides']);

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->mountTableAction('create')
            ->assertSet('mountedActions.0.data.audience_id', null);
    }

    public function test_a_requirement_category_stays_hidden_until_a_group_is_chosen(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $activity = Activity::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ActivityAudiencesRelationManager::class, [
            'ownerRecord' => $activity,
            'pageClass' => EditActivity::class,
        ])->mountTableAction('create')
            ->fillForm([
                'qualificationRequirements' => [
                    ['kind' => 'scout_permit'],
                ],
            ])
            ->assertFormFieldIsHidden('qualificationRequirements.0.node')
            ->fillForm([
                'qualificationRequirements' => [
                    [
                        'kind' => 'scout_permit',
                        'group' => 'Canoeing River',
                    ],
                ],
            ])
            ->assertFormFieldIsVisible('qualificationRequirements.0.node')
            ->assertFormFieldIsVisible('qualificationRequirements.0.permit_type');
    }

    private function pairing(Activity $activity): ActivityAudience
    {
        $existing = $activity->activityAudiences()->first();

        if ($existing instanceof ActivityAudience) {
            return $existing;
        }

        return ActivityAudience::factory()->for($activity)->create();
    }
}
