<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Models\Activity;
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
        $member = User::factory()->teamMember()->create();

        $this->assertFalse($activity->requiresQualification());
        $this->assertSame(0, $member->qualifications()->count());
        $this->assertTrue($activity->metBy($member));
    }

    public function test_any_current_qualification_that_covers_a_row_is_enough(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
            'kind' => 'mountain_training',
            'criteria' => ['award' => 'Climbing Wall Instructor'],
        ]);
        $activity->qualificationRequirements()->create([
            'kind' => 'girlguiding',
        ]);

        $member = User::factory()->teamMember()->create();
        $this->assertFalse($activity->fresh()->metBy($member));

        Qualification::factory()->forMember($member)->mountainTraining('Rock Climbing Instructor')->create();

        $this->assertTrue($activity->fresh()->metBy($member->fresh()));

        $other = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($other)->mountainTraining('Lowland Leader')->create();

        $this->assertFalse($activity->fresh()->metBy($other));

        $guide = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($guide)->girlguiding('2')->create();

        $this->assertTrue($activity->fresh()->metBy($guide));
    }

    public function test_a_scout_permit_matches_on_activity_category_and_type_and_ignores_restrictions(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
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

        $this->assertTrue($activity->fresh()->metBy($member));

        $personal = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($personal)->scoutPermit([
            'permit_type' => 'personal',
            'restrictions' => 'Unrestricted',
        ])->create();

        $this->assertFalse($activity->fresh()->metBy($personal));

        $rock = Activity::factory()->create();
        $rock->qualificationRequirements()->create([
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

        $this->assertFalse($rock->fresh()->metBy($tower));

        $expired = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($expired)->scoutPermit([
            'permit_type' => 'leadership',
        ])->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($activity->fresh()->metBy($expired));
    }

    public function test_a_river_permit_does_not_meet_an_open_inland_requirement(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
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

        $this->assertFalse($activity->fresh()->metBy($river));

        $inland = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($inland)->scoutPermit([
            'activity' => 'Canoeing Open Inland',
            'category' => 'B2',
            'permit_type' => 'leadership',
        ])->create();

        $this->assertTrue($activity->fresh()->metBy($inland));

        $sameRiver = Activity::factory()->create();
        $sameRiver->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Canoeing River',
                'category' => 'B1',
            ],
        ]);

        $this->assertTrue($sameRiver->fresh()->metBy($river));
    }

    public function test_a_permit_must_still_be_valid_on_the_booking_end_date(): void
    {
        $this->travelTo('2026-10-25 12:00:00');

        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
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

        $this->assertTrue($activity->fresh()->metBy($member));

        $booking = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-11-02 09:30:00',
            'end_at' => '2026-11-02 16:00:00',
            'timezone' => 'Europe/London',
        ]);

        $this->assertFalse($activity->fresh()->metBy($member, $booking));

        $lastDay = Booking::factory()->create([
            'activity_id' => $activity->getKey(),
            'start_at' => '2026-11-01 09:30:00',
            'end_at' => '2026-11-01 16:00:00',
            'timezone' => 'Europe/London',
        ]);

        $this->assertTrue($activity->fresh()->metBy($member, $lastDay));

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
        $activity->qualificationRequirements()->create([
            'kind' => 'nsra',
            'criteria' => ['award' => 'Club Instructor'],
        ]);

        $this->assertSame(
            'NSRA — Club Instructor',
            $activity->qualificationRequirements()->first()?->summary,
        );

        $coach = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($coach)->nsra('Club Coach')->create();

        $this->assertTrue($activity->fresh()->metBy($coach));

        $tutor = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($tutor)->nsra('YPS Tutor')->create();

        $this->assertFalse($activity->fresh()->metBy($tutor));
    }

    public function test_an_archery_gb_requirement_does_not_treat_a_coach_as_an_instructor(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
            'kind' => 'archery_gb_awards',
            'criteria' => ['award' => 'Instructor Award'],
        ]);

        $this->assertSame(
            'Archery GB — Instructor Award',
            $activity->qualificationRequirements()->first()?->summary,
        );

        $plus = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($plus)->archeryGb('Instructor Plus')->create();

        $this->assertTrue($activity->fresh()->metBy($plus));

        $coach = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($coach)->archeryGb('Session Coach')->create();

        $this->assertFalse($activity->fresh()->metBy($coach));
    }

    public function test_a_requirement_does_not_keep_a_category_without_a_group(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'category' => 'B1',
            ],
        ]);

        $this->assertSame([], $activity->qualificationRequirements()->first()?->criteria);
    }

    public function test_a_system_admin_can_set_and_clear_alternatives(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();

        $this->actingAs($manager);
        $this->get(ActivityResource::getUrl('create'))->assertForbidden();

        $this->actingAs($admin);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'name' => 'Scout climbing',
                'qualificationRequirements' => [
                    [
                        'kind' => 'scout_permit',
                        'group' => 'Climbing and Abseiling',
                        'permit_type' => 'leadership',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('name', 'Scout climbing')->first();
        $this->assertNotNull($activity);
        $this->assertTrue($activity->requiresQualification());
        $this->assertSame(
            'Scout permit — Climbing and Abseiling — Leadership',
            $activity->qualificationRequirements()->first()?->summary,
        );

        $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
            ->assertOk()
            ->assertSee('Scout permit — Climbing and Abseiling — Leadership');

        Livewire::test(EditActivity::class, ['record' => $activity->getKey()])
            ->fillForm([
                'qualificationRequirements' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $activity->refresh();
        $this->assertFalse($activity->requiresQualification());

        $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
            ->assertOk()
            ->assertSee('No qualification required.');
    }

    public function test_a_requirement_row_stores_one_scout_group(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'name' => 'Canoeing',
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
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('name', 'Canoeing')->first();
        $this->assertNotNull($activity);
        $this->assertSame(2, $activity->qualificationRequirements()->count());
        $this->assertSame(
            ['Canoeing Open Inland', 'Canoeing River'],
            $activity->qualificationRequirements()->orderBy('sort')->get()->map(fn ($row) => $row->criteria['activity'])->all(),
        );
    }

    public function test_a_requirement_category_stays_hidden_until_a_group_is_chosen(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'name' => 'Canoeing',
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
}
