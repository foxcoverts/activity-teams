<?php

namespace Tests\Feature;

use App\Enums\GirlguidingLevel;
use App\Enums\MembershipRole;
use App\Enums\MountainTrainingAward;
use App\Enums\ScoutPermitActivity;
use App\Enums\ScoutPermitCategory;
use App\Enums\ScoutPermitType;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Filament\Manage\Resources\Members\RelationManagers\QualificationsRelationManager;
use App\Filament\Team\Resources\Qualifications\Pages\ListQualifications;
use App\Filament\Team\Resources\Qualifications\QualificationResource;
use App\Models\MountainTrainingQualification;
use App\Models\Qualification;
use App\Models\ScoutPermit;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Tests\TestCase;

class QualificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_the_expiry_date_is_the_last_day_the_qualification_is_current(): void
    {
        $member = User::factory()->teamMember()->create();
        $qualification = Qualification::factory()->forMember($member)->create([
            'expires_on' => '2030-06-15',
        ]);

        $this->travelTo('2030-06-15 18:00:00');
        $this->assertFalse($qualification->fresh()->isExpired());

        $this->travelTo('2030-06-16 00:00:01');
        $this->assertTrue($qualification->fresh()->isExpired());
    }

    public function test_mountain_training_does_not_expire_and_an_award_is_held_once(): void
    {
        $member = User::factory()->teamMember()->create();

        $qualification = Qualification::record($member, new MountainTrainingQualification([
            'award' => MountainTrainingAward::ClimbingWallInstructor,
        ]));

        $this->assertNull($qualification->expires_on);
        $this->assertFalse($qualification->isExpired());
        $this->assertSame('Climbing Wall Instructor', $qualification->summary);

        $this->expectException(ValidationException::class);

        Qualification::record($member, new MountainTrainingQualification([
            'award' => MountainTrainingAward::ClimbingWallInstructor,
        ]));
    }

    public function test_a_climbing_permit_needs_a_category_and_archery_does_not(): void
    {
        $member = User::factory()->teamMember()->create();

        $archery = Qualification::record($member, new ScoutPermit([
            'activity' => ScoutPermitActivity::Archery,
            'category' => ScoutPermitCategory::ArtificialTopRope,
            'permit_type' => ScoutPermitType::Supervisory,
            'restrictions' => 'Indoor range only',
        ]), '2030-01-01');

        $this->assertNull($archery->detail->category);
        $this->assertSame('Archery — Supervisory', $archery->summary);
        $this->assertSame('Indoor range only', $archery->restrictionNote);

        $this->expectException(ValidationException::class);

        Qualification::record($member, new ScoutPermit([
            'activity' => ScoutPermitActivity::ClimbingAndAbseiling,
            'permit_type' => ScoutPermitType::Leadership,
        ]), '2030-01-01');
    }

    public function test_deleting_a_qualification_or_a_person_deletes_the_detail(): void
    {
        $member = User::factory()->teamMember()->create();
        $qualification = Qualification::factory()->forMember($member)->create();
        $detailId = $qualification->detail_id;

        $qualification->delete();

        $this->assertDatabaseMissing('qualifications', ['id' => $qualification->getKey()]);
        $this->assertDatabaseMissing('scout_permits', ['id' => $detailId]);

        $again = Qualification::factory()->forMember($member)->girlguiding(GirlguidingLevel::Two)->create();
        $girlguidingId = $again->detail_id;
        $member->delete();

        $this->assertDatabaseMissing('qualifications', ['id' => $again->getKey()]);
        $this->assertDatabaseMissing('girlguiding_qualifications', ['id' => $girlguidingId]);
    }

    public function test_a_manager_can_record_edit_and_remove_a_current_members_qualification(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $team = $leader->managedTeams()->first();
        $oversight = User::factory()->oversight()->create();

        $this->actingAs($leader);
        Filament::setTenant($team);

        $manager = Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->assertSee('Add qualification')
            ->assertDontSee('New qualification');

        $create = collect($manager->instance()->getTable()->getHeaderActions())
            ->first(fn ($action): bool => $action->getName() === 'create');
        $this->assertSame('Add Qualification', $create->getModalHeading());

        $manager->callTableAction('create', data: [
            'detail_type' => ScoutPermit::class,
            'activity' => ScoutPermitActivity::ClimbingAndAbseiling->value,
            'category' => ScoutPermitCategory::NaturalTopRope->value,
            'permit_type' => ScoutPermitType::Leadership->value,
            'restrictions' => 'No night climbing',
            'expires_on' => '2030-06-15',
        ])->assertSee('Climbing and abseiling — Natural top rope — Leadership')
            ->assertDontSee('No night climbing');

        $qualification = $member->qualifications()->with('detail')->first();
        $this->assertInstanceOf(Qualification::class, $qualification);
        $this->assertSame('No night climbing', $qualification->restrictionNote);
        $this->assertFalse($qualification->isExpired());
        $this->assertTrue(ActivityLog::query()
            ->where('subject_type', $qualification->getMorphClass())
            ->where('subject_id', $qualification->getKey())
            ->where('causer_id', $leader->getKey())
            ->exists());

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->callTableAction('edit', $qualification, data: [
            'detail' => [
                'activity' => ScoutPermitActivity::ClimbingAndAbseiling->value,
                'category' => ScoutPermitCategory::NaturalLeadClimbing->value,
                'permit_type' => ScoutPermitType::Leadership->value,
                'restrictions' => 'No night climbing',
            ],
            'expires_on' => '2031-01-01',
        ])->assertSee('Natural lead climbing');

        $this->assertSame(ScoutPermitCategory::NaturalLeadClimbing, $qualification->fresh()->detail->category);

        $deputy = User::factory()->teamLeader()->create();
        $deputy->managedTeams()->first()->grant($member, MembershipRole::Member);
        $this->assertTrue($deputy->can('update', $qualification));
        $this->assertTrue($oversight->can('view', $qualification));
        $this->assertFalse($oversight->can('update', $qualification));
        $this->assertTrue($member->can('view', $qualification));
        $this->assertFalse($member->can('update', $qualification));

        $member->update(['suspended' => true]);
        $this->assertFalse(Qualification::canMaintain($leader, $member->fresh()));

        $this->actingAs($oversight);
        Filament::setTenant($team);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewMember::class,
        ])->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $qualification)
            ->assertTableActionHidden('delete', $qualification)
            ->assertSee('Natural lead climbing');

        $this->actingAs($leader);
        Filament::setTenant($team);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewMember::class,
        ])->assertTableActionHidden('create');

        $member->update(['suspended' => false]);

        $this->actingAs($leader);
        Filament::setTenant($team);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewMember::class,
        ])->callTableAction('delete', $qualification);

        $this->assertDatabaseMissing('qualifications', ['id' => $qualification->getKey()]);
        $this->assertDatabaseMissing('scout_permits', ['id' => $qualification->detail_id]);
    }

    public function test_a_member_sees_their_own_qualifications_in_the_team_menu(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();

        $permit = Qualification::factory()->forMember($member)->scoutPermit([
            'restrictions' => 'No night climbing',
        ])->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);
        $award = Qualification::factory()->forMember($member)->mountainTraining()->create();
        $other = Qualification::factory()->forMember($leader)->mountainTraining(MountainTrainingAward::MountainLeader)->create();

        Filament::setCurrentPanel(Filament::getPanel('team'));
        $this->actingAs($member);

        $this->get(QualificationResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Qualifications')
            ->assertSee('Climbing and abseiling — Artificial top rope — Leadership')
            ->assertSee('Climbing Wall Instructor')
            ->assertSee('These are the qualifications recorded for you.')
            ->assertDontSee('No night climbing')
            ->assertDontSee('Restrictions')
            ->assertDontSee('Mountain Leader')
            ->assertDontSee('/manage/')
            ->assertDontSee('New qualification');

        Livewire::test(ListQualifications::class)
            ->assertTableActionVisible('view', $permit);

        $this->get(QualificationResource::getUrl('view', ['record' => $permit]))
            ->assertOk()
            ->assertSee('No night climbing')
            ->assertSee('Climbing and abseiling')
            ->assertSee('Artificial top rope')
            ->assertSee('Leadership')
            ->assertSee('Expires')
            ->assertDontSee('/manage/');

        $this->get(QualificationResource::getUrl('view', ['record' => $award]))
            ->assertOk()
            ->assertSee('Climbing Wall Instructor')
            ->assertDontSee('Expires')
            ->assertDontSee('Restrictions')
            ->assertDontSee('Permit type');

        $this->get(QualificationResource::getUrl('view', ['record' => $other]))
            ->assertNotFound();

        Livewire::test(EditProfile::class)
            ->assertDontSee('Qualifications')
            ->assertDontSee('Climbing Wall Instructor');

        $empty = User::factory()->teamMember()->create();
        $this->actingAs($empty);

        Livewire::test(ListQualifications::class)
            ->assertSee('No qualifications recorded.');
    }
}
