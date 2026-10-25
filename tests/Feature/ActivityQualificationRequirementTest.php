<?php

namespace Tests\Feature;

use App\Enums\GirlguidingLevel;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Activities\Pages\EditActivity;
use App\Models\Activity;
use App\Models\GirlguidingQualification;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
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
            'detail_type' => MountainTrainingQualification::class,
            'award' => [MountainTrainingAward::ClimbingWallInstructor->value],
        ]);
        $activity->qualificationRequirements()->create([
            'detail_type' => GirlguidingQualification::class,
        ]);

        $member = User::factory()->teamMember()->create();
        $this->assertFalse($activity->fresh()->metBy($member));

        Qualification::factory()->forMember($member)->mountainTraining(MountainTrainingAward::RockClimbingInstructor)->create();

        $this->assertTrue($activity->fresh()->metBy($member->fresh()));

        $other = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($other)->mountainTraining(MountainTrainingAward::ClimbingWallDevelopmentInstructor)->create();

        $this->assertFalse($activity->fresh()->metBy($other));

        $guide = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($guide)->girlguiding(GirlguidingLevel::Two)->create();

        $this->assertTrue($activity->fresh()->metBy($guide));
    }

    public function test_a_scout_permit_matches_on_activity_category_and_type_and_ignores_restrictions(): void
    {
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
            'detail_type' => ScoutPermit::class,
            'permit_activity' => [ScoutPermitActivity::ClimbingAndAbseiling->value],
            'category' => [ScoutPermitCategory::ArtificialTopRope->value],
            'permit_type' => [ScoutPermitType::Leadership->value],
        ]);

        $member = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($member)->scoutPermit([
            'category' => ScoutPermitCategory::NaturalLeadClimbing,
            'permit_type' => ScoutPermitType::Supervisory,
            'restrictions' => 'No night climbing',
        ])->create();

        $this->assertTrue($activity->fresh()->metBy($member));

        $personal = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($personal)->scoutPermit([
            'permit_type' => ScoutPermitType::Personal,
            'restrictions' => 'Unrestricted',
        ])->create();

        $this->assertFalse($activity->fresh()->metBy($personal));

        $rock = Activity::factory()->create();
        $rock->qualificationRequirements()->create([
            'detail_type' => ScoutPermit::class,
            'permit_activity' => [ScoutPermitActivity::ClimbingAndAbseiling->value],
            'category' => [ScoutPermitCategory::NaturalTopRope->value],
            'permit_type' => [ScoutPermitType::Leadership->value],
        ]);

        $tower = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($tower)->scoutPermit([
            'category' => ScoutPermitCategory::ArtificialTopRope,
            'permit_type' => ScoutPermitType::Leadership,
        ])->create();

        $this->assertFalse($rock->fresh()->metBy($tower));

        $expired = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($expired)->scoutPermit([
            'permit_type' => ScoutPermitType::Leadership,
        ])->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($activity->fresh()->metBy($expired));
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
                        'detail_type' => ScoutPermit::class,
                        'permit_activity' => [ScoutPermitActivity::ClimbingAndAbseiling->value],
                        'permit_type' => [ScoutPermitType::Leadership->value],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->where('name', 'Scout climbing')->first();
        $this->assertNotNull($activity);
        $this->assertTrue($activity->requiresQualification());
        $this->assertSame(
            'Scout permit — Climbing and abseiling — Leadership',
            $activity->qualificationRequirements()->first()?->summary,
        );

        $this->get(ActivityResource::getUrl('view', ['record' => $activity]))
            ->assertOk()
            ->assertSee('Scout permit — Climbing and abseiling — Leadership');

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
}
