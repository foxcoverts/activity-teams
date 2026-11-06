<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Filament\Member\Resources\Qualifications\Pages\ListQualifications;
use App\Filament\Member\Resources\Qualifications\QualificationResource;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
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

        Filament::setCurrentPanel('manage');
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

        $qualification = Qualification::record($member, 'mountain_training', [
            'award' => 'Climbing Wall Instructor',
        ]);

        $this->assertNull($qualification->expires_on);
        $this->assertFalse($qualification->isExpired());
        $this->assertSame('Climbing Wall Instructor', $qualification->summary);
        $this->assertNull($qualification->expiryColor());

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'mountain_training', [
            'award' => 'Climbing Wall Instructor',
        ]);
    }

    public function test_an_nsra_award_expires_and_is_held_once(): void
    {
        $member = User::factory()->teamMember()->create();

        $qualification = Qualification::record($member, 'nsra', [
            'award' => 'Club Instructor',
        ], '2030-01-01');

        $this->assertSame('Club Instructor', $qualification->summary);
        $this->assertSame('NSRA', $qualification->type_label);

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'nsra', [
            'award' => 'Club Instructor',
        ], '2030-01-01');
    }

    public function test_an_archery_gb_award_expires_and_is_held_once(): void
    {
        $member = User::factory()->teamMember()->create();

        $qualification = Qualification::record($member, 'archery_gb_awards', [
            'award' => 'Instructor Award',
        ], '2030-01-01');

        $this->assertSame('Instructor Award', $qualification->summary);
        $this->assertSame('Archery GB', $qualification->type_label);

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'archery_gb_awards', [
            'award' => 'Instructor Award',
        ], '2030-01-01');
    }

    public function test_a_scout_permit_needs_a_category_from_its_group(): void
    {
        $member = User::factory()->teamMember()->create();

        $archery = Qualification::record($member, 'scout_permit', [
            'activity' => 'Archery',
            'category' => 'without Compound Bows',
            'permit_type' => 'supervisory',
            'restrictions' => 'Indoor range only',
        ], '2030-01-01');

        $this->assertSame('without Compound Bows', $archery->payload['category']);
        $this->assertSame('Archery — without Compound Bows — Supervisory', $archery->summary);
        $this->assertSame('Indoor range only', $archery->restrictionNote);

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'scout_permit', [
            'activity' => 'Climbing and Abseiling',
            'permit_type' => 'leadership',
        ], '2030-01-01');
    }

    public function test_archery_rejects_a_personal_permit_and_climbing_rejects_a_leftover_archery_category(): void
    {
        $member = User::factory()->teamMember()->create();

        try {
            Qualification::record($member, 'scout_permit', [
                'activity' => 'Archery',
                'category' => 'without Compound Bows',
                'permit_type' => 'personal',
            ], '2030-01-01');
            $this->fail('Archery personal permits should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'scout_permit', [
            'activity' => 'Climbing and Abseiling',
            'category' => 'without Compound Bows',
            'permit_type' => 'leadership',
        ], '2030-01-01');
    }

    public function test_deleting_a_qualification_removes_the_row(): void
    {
        $member = User::factory()->teamMember()->create();
        $qualification = Qualification::factory()->forMember($member)->create();

        $qualification->delete();

        $this->assertDatabaseMissing('qualifications', ['id' => $qualification->getKey()]);
    }

    public function test_soft_deleting_a_person_keeps_their_qualifications(): void
    {
        $member = User::factory()->teamMember()->create();
        $qualification = Qualification::factory()->forMember($member)->girlguiding('2')->create();

        $member->delete();

        $this->assertTrue($member->trashed());
        $this->assertDatabaseHas('qualifications', ['id' => $qualification->getKey()]);
    }

    public function test_force_deleting_a_person_deletes_their_qualifications(): void
    {
        $member = User::factory()->teamMember()->create();
        $qualification = Qualification::factory()->forMember($member)->girlguiding('2')->create();

        $member->forceDelete();

        $this->assertDatabaseMissing('qualifications', ['id' => $qualification->getKey()]);
    }

    public function test_a_manager_can_record_edit_and_remove_a_current_members_qualification(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();
        $oversight = User::factory()->oversight()->create();

        $this->actingAs($manager);

        $relationManager = Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->assertSee('Add qualification')
            ->assertDontSee('New qualification');

        $create = collect($relationManager->instance()->getTable()->getHeaderActions())
            ->first(fn ($action): bool => $action->getName() === 'create');
        $this->assertSame('Add Qualification', $create->getModalHeading());

        $relationManager->callTableAction('create', data: [
            'kind' => 'scout_permit',
            'group' => 'Climbing and Abseiling',
            'node' => 'Natural Top Rope',
            'permit_type' => 'leadership',
            'restrictions' => 'No night climbing',
            'expires_on' => '2030-06-15',
        ])->assertSee('Climbing and Abseiling — Natural Top Rope — Leadership')
            ->assertDontSee('No night climbing');

        $qualification = $member->qualifications()->first();
        $this->assertInstanceOf(Qualification::class, $qualification);
        $this->assertSame('No night climbing', $qualification->restrictionNote);
        $this->assertFalse($qualification->isExpired());
        $this->assertTrue(ActivityLog::query()
            ->where('subject_type', $qualification->getMorphClass())
            ->where('subject_id', $qualification->getKey())
            ->where('causer_id', $manager->getKey())
            ->exists());

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->callTableAction('edit', $qualification, data: [
            'kind' => 'scout_permit',
            'group' => 'Climbing and Abseiling',
            'node' => 'Natural Lead Climbing',
            'permit_type' => 'leadership',
            'restrictions' => 'No night climbing',
            'expires_on' => '2031-01-01',
        ])->assertSee('Natural Lead Climbing');

        $this->assertSame('Natural Lead Climbing', $qualification->fresh()->payload['category']);

        $otherTeam = Team::factory()->create();
        $otherManager = User::factory()->teamManager($otherTeam)->create();
        $otherTeam->grant($member, MembershipRole::Member);
        $this->assertTrue($otherManager->can('update', $qualification));
        $this->assertTrue($oversight->can('view', $qualification));
        $this->assertFalse($oversight->can('update', $qualification));
        $this->assertTrue($member->can('view', $qualification));
        $this->assertFalse($member->can('update', $qualification));

        $member->update(['suspended' => true]);
        $this->assertFalse(Qualification::canMaintain($manager, $member->fresh()));

        Filament::setCurrentPanel('admin');
        $this->actingAs($oversight);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewUser::class,
        ])->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $qualification)
            ->assertTableActionHidden('delete', $qualification)
            ->assertSee('Natural Lead Climbing');

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewMember::class,
        ])->assertTableActionHidden('create');

        $member->update(['suspended' => false]);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member->fresh(),
            'pageClass' => ViewMember::class,
        ])->callTableAction('delete', $qualification);

        $this->assertDatabaseMissing('qualifications', ['id' => $qualification->getKey()]);
    }

    public function test_a_girlguiding_award_needs_a_scheme_before_a_level(): void
    {
        $member = User::factory()->teamMember()->create();

        $award = Qualification::record($member, 'girlguiding', [
            'scheme' => 'Climbing',
            'level' => '2',
        ], '2030-01-01');

        $this->assertSame('Climbing — Level 2', $award->summary);

        $this->expectException(ValidationException::class);

        Qualification::record($member, 'girlguiding', [
            'level' => '1',
        ], '2030-01-01');
    }

    public function test_changing_the_permit_group_clears_an_invalid_category(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->mountTableAction('create')
            ->fillForm([
                'kind' => 'scout_permit',
                'group' => 'Climbing and Abseiling',
                'node' => 'Artificial Top Rope',
            ])
            ->fillForm([
                'group' => 'Canoeing River',
            ])
            ->assertFormSet([
                'node' => null,
            ]);
    }

    public function test_girlguiding_hides_level_until_a_scheme_is_chosen(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->mountTableAction('create')
            ->fillForm([
                'kind' => 'girlguiding',
            ])
            ->assertFormFieldIsVisible('group')
            ->assertFormFieldIsHidden('node')
            ->fillForm([
                'kind' => 'girlguiding',
                'group' => 'Climbing',
            ])
            ->assertFormFieldIsVisible('node');
    }

    public function test_multi_pitch_does_not_accept_a_supervisory_permit(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();

        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->callTableAction('create', data: [
            'kind' => 'scout_permit',
            'group' => 'Climbing and Abseiling',
            'node' => 'Multi Pitch',
            'permit_type' => 'supervisory',
            'expires_on' => '2030-06-15',
        ])->assertHasActionErrors();

        $this->assertSame(0, $member->qualifications()->count());
    }

    public function test_expiry_colour_is_green_then_amber_then_red(): void
    {
        $member = User::factory()->teamMember()->create();

        $this->travelTo('2026-10-25 12:00:00');

        $current = Qualification::factory()->forMember($member)->create([
            'expires_on' => '2027-02-25',
        ]);
        $due = Qualification::factory()->forMember($member)->create([
            'expires_on' => '2027-01-25',
        ]);
        $expired = Qualification::factory()->forMember($member)->create([
            'expires_on' => '2026-10-24',
        ]);

        $this->assertSame(Palette::positive(), $current->expiryColor());
        $this->assertSame(Palette::uncertain(), $due->expiryColor());
        $this->assertSame(Palette::destructive(), $expired->expiryColor());
    }

    public function test_a_member_sees_their_own_qualifications_in_the_team_menu(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();

        $permit = Qualification::factory()->forMember($member)->scoutPermit([
            'restrictions' => 'No night climbing',
        ])->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);
        $award = Qualification::factory()->forMember($member)->mountainTraining()->create();
        $other = Qualification::factory()->forMember($manager)->mountainTraining('Mountain Leader')->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $this->get(QualificationResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Qualifications')
            ->assertDontSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertSee('Climbing Wall Instructor')
            ->assertSee('These are the qualifications recorded for you.')
            ->assertDontSee('No night climbing')
            ->assertDontSee('Restrictions')
            ->assertDontSee('Mountain Leader')
            ->assertDontSee('/manage/')
            ->assertDontSee('New qualification');

        Livewire::test(ListQualifications::class)
            ->assertDontSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->filterTable('expired', true)
            ->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertTableActionVisible('view', $permit);

        $this->get(QualificationResource::getUrl('view', ['record' => $permit]))
            ->assertOk()
            ->assertSee('No night climbing')
            ->assertSee('Climbing and Abseiling')
            ->assertSee('Artificial Top Rope')
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

    public function test_a_manager_hides_expired_qualifications_until_asked(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($member)->scoutPermit()->create([
            'expires_on' => now()->subDay()->toDateString(),
        ]);
        Qualification::factory()->forMember($member)->mountainTraining()->create();

        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->assertDontSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertSee('Climbing Wall Instructor')
            ->filterTable('expired', true)
            ->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership');
    }

    public function test_a_manager_can_filter_a_members_qualifications_by_type(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        Qualification::factory()->forMember($member)->scoutPermit()->create();
        Qualification::factory()->forMember($member)->mountainTraining()->create();
        Qualification::factory()->forMember($member)->girlguiding()->create();

        $this->actingAs($manager);

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertSee('Climbing Wall Instructor')
            ->assertSee('Climbing — Level 1')
            ->filterTable('kind', 'scout_permit')
            ->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertDontSee('Climbing Wall Instructor')
            ->assertDontSee('Climbing — Level 1')
            ->filterTable('kind', 'mountain_training')
            ->assertDontSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertSee('Climbing Wall Instructor')
            ->assertDontSee('Climbing — Level 1')
            ->filterTable('kind', 'girlguiding')
            ->assertDontSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertDontSee('Climbing Wall Instructor')
            ->assertSee('Climbing — Level 1');
    }
}
