<?php

namespace Tests\Feature;

use App\Actions\AcknowledgePersonalDataAction;
use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Manage\Resources\Members\Pages\EditMember;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Models\PersonalDataAcknowledgement;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PersonalDataAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamManager()->create();
        $this->member = User::factory()->teamMember()->create([
            'name' => 'Alex Member',
            'email' => 'alex@example.test',
            'phone' => '07000111222',
            'emergency_name' => 'Sam Emergency',
            'emergency_phone' => '07000333444',
        ]);
    }

    public function test_details_are_not_in_the_page_until_acknowledged(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        $this->get(MemberResource::getUrl('view', ['record' => $this->member]))
            ->assertOk()
            ->assertSee('I have a legitimate reason to view these details')
            ->assertDontSee('alex@example.test')
            ->assertDontSee('07000111222')
            ->assertDontSee('Sam Emergency')
            ->assertDontSee('07000333444');

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->oversight()->create());

        $this->get(UserResource::getUrl('view', ['record' => $this->member]))
            ->assertOk()
            ->assertSee('I have a legitimate reason to view these details')
            ->assertDontSee('alex@example.test')
            ->assertDontSee('07000111222')
            ->assertDontSee('Sam Emergency')
            ->assertDontSee('07000333444');
    }

    public function test_each_section_is_acknowledged_separately(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value)
            ->assertSee('alex@example.test')
            ->assertSee('+44 70 0011 1222')
            ->assertDontSee('Sam Emergency')
            ->assertDontSee('07000333444')
            ->call('acknowledgePersonalData', PersonalDataSection::EmergencyContact->value)
            ->assertSee('Sam Emergency')
            ->assertSee('+44 70 0033 3444');

        $this->assertDatabaseHas('personal_data_acknowledgements', [
            'viewer_id' => $this->leader->getKey(),
            'viewer_name' => $this->leader->name,
            'subject_id' => $this->member->getKey(),
            'subject_name' => 'Alex Member',
            'section' => PersonalDataSection::ContactDetails->value,
        ]);
        $this->assertSame(2, PersonalDataAcknowledgement::query()->count());
    }

    public function test_acknowledging_one_person_does_not_reveal_another(): void
    {
        $other = User::factory()->teamMember()->create(['email' => 'other@example.test']);

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->oversight()->create());

        Livewire::test(ViewUser::class, ['record' => $this->member->getKey()])
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value);

        Livewire::test(ViewUser::class, ['record' => $other->getKey()])
            ->assertDontSee('other@example.test');
    }

    public function test_an_acknowledgement_lasts_thirty_minutes_then_expires(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value);

        $this->travel(29)->minutes();

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertSee('alex@example.test');

        $this->travel(2)->minutes();

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertDontSee('alex@example.test');
    }

    public function test_an_acknowledgement_by_someone_else_does_not_count(): void
    {
        $managerTeam = $this->leader->managedTeams()->first();
        $deputy = User::factory()->teamManager($managerTeam)->create();

        Filament::setCurrentPanel('manage');
        $this->actingAs($deputy);
        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value);

        $this->actingAs($this->leader);
        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertDontSee('alex@example.test');
    }

    public function test_people_can_view_their_own_details_without_acknowledging(): void
    {
        $this->leader->update(['email' => 'leader@example.test', 'emergency_name' => 'Lee Emergency']);

        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(ViewMember::class, ['record' => $this->leader->getKey()])
            ->assertSee('leader@example.test')
            ->assertSee('Lee Emergency')
            ->assertDontSee('I have a legitimate reason to view these details');

        $this->assertDatabaseCount('personal_data_acknowledgements', 0);
    }

    public function test_acknowledging_does_not_grant_access_to_someone_who_cannot_view(): void
    {
        $outsiderTeam = Team::factory()->create();
        $outsider = User::factory()->teamManager($outsiderTeam)->create();

        $this->assertFalse($outsider->can('view', $this->member));

        Filament::setCurrentPanel('manage');
        $this->actingAs($outsider);

        $this->get(MemberResource::getUrl('view', ['record' => $this->member], tenant: $outsiderTeam))
            ->assertNotFound();

        $this->assertDatabaseCount('personal_data_acknowledgements', 0);
    }

    public function test_a_system_admin_can_grant_oversight_but_still_needs_to_acknowledge(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        $this->get(UserResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Alex Member')
            ->assertDontSee('alex@example.test');

        Livewire::test(ViewUser::class, ['record' => $this->member->getKey()])
            ->assertDontSee('alex@example.test')
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value)
            ->assertSee('alex@example.test');

        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->fillForm(['oversight' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->member->fresh()->oversight);
    }

    public function test_the_member_edit_form_hides_details_until_acknowledged(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->assertSee('I have a legitimate reason to view these details')
            ->assertSet('data.email', null)
            ->assertSet('data.phone', null)
            ->assertSet('data.emergency_name', null)
            ->fillForm(['name' => 'Alex Renamed'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value)
            ->assertSet('data.email', 'alex@example.test')
            ->assertSet('data.emergency_name', null);

        $this->member->refresh();
        $this->assertSame('Alex Renamed', $this->member->name);
        $this->assertSame('alex@example.test', $this->member->email);
        $this->assertSame('Sam Emergency', $this->member->emergency_name);

        Livewire::test(EditMember::class, ['record' => $this->member->getKey()])
            ->assertSee('Email verified')
            ->assertSet('data.email', 'alex@example.test')
            ->fillForm(['phone' => '07999888777'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('07999888777', $this->member->fresh()->phone);
        $this->assertSame('alex@example.test', $this->member->fresh()->email);
    }

    public function test_only_system_admins_can_set_access_flags(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->oversight()->create());

        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->assertFormFieldHidden('oversight')
            ->assertFormFieldHidden('is_system_admin')
            ->fillForm(['name' => 'Alex Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->member->refresh();
        $this->assertSame('Alex Renamed', $this->member->name);
        $this->assertFalse($this->member->oversight);
        $this->assertFalse($this->member->is_system_admin);

        $this->actingAs(User::factory()->systemAdmin()->create());

        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->assertFormFieldVisible('oversight')
            ->assertFormFieldVisible('is_system_admin')
            ->fillForm(['oversight' => true, 'is_system_admin' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->member->fresh()->oversight);
        $this->assertTrue($this->member->fresh()->is_system_admin);
    }

    public function test_personal_details_are_not_written_to_the_activity_log(): void
    {
        $this->member->update(['name' => 'Alex Renamed', 'phone' => '07999888777', 'emergency_name' => 'Pat Other']);
        $this->member->update(['email' => 'new@example.test']);

        $logged = Activity::query()
            ->where('subject_type', $this->member->getMorphClass())
            ->where('subject_id', $this->member->getKey())
            ->get()
            ->toJson();

        $this->assertStringContainsString('Alex Renamed', $logged);
        foreach (['alex@example.test', 'new@example.test', '07000111222', '07999888777', 'Sam Emergency', 'Pat Other'] as $value) {
            $this->assertStringNotContainsString($value, $logged);
        }
    }

    public function test_the_user_screen_shows_status_badges_instead_of_dates(): void
    {
        $this->member->forceFill([
            'oversight' => true,
            'is_system_admin' => true,
            'suspended' => true,
            'email_verified_at' => now(),
        ])->save();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->oversight()->create());

        Livewire::test(ViewUser::class, ['record' => $this->member->getKey()])
            ->assertSee('Email verified')
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Suspended,
                    MembershipRole::Member,
                ],
            ])
            ->assertDontSee('Created at')
            ->assertDontSee('Updated at')
            ->assertDontSee('Email verified at');

        $this->member->forceFill(['suspended' => false])->save();

        Livewire::test(ViewUser::class, ['record' => $this->member->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Active,
                    MembershipRole::Member,
                    AccountRole::SystemAdmin,
                    AccountRole::Oversight,
                ],
            ]);

        $this->member->forceFill([
            'oversight' => false,
            'is_system_admin' => false,
            'email_verified_at' => null,
        ])->save();

        Livewire::test(ViewUser::class, ['record' => $this->member->getKey()])
            ->assertSee('Email not verified')
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Active,
                    MembershipRole::Member,
                ],
            ]);
    }

    public function test_the_member_screen_shows_whether_their_email_is_verified(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertSee('Email verified')
            ->assertDontSee('Email not verified');

        $this->member->forceFill(['email_verified_at' => null])->save();

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->assertSee('Email not verified')
            ->assertDontSee('alex@example.test');
    }

    public function test_an_unknown_section_is_rejected(): void
    {
        Filament::setCurrentPanel('manage');
        $this->actingAs($this->leader);

        Livewire::test(ViewMember::class, ['record' => $this->member->getKey()])
            ->call('acknowledgePersonalData', 'everything');

        $this->assertDatabaseCount('personal_data_acknowledgements', 0);
    }

    public function test_repeating_a_current_acknowledgement_does_not_add_a_record(): void
    {
        app(AcknowledgePersonalDataAction::class)($this->leader, $this->member, PersonalDataSection::ContactDetails);
        app(AcknowledgePersonalDataAction::class)($this->leader, $this->member, PersonalDataSection::ContactDetails);

        $this->assertDatabaseCount('personal_data_acknowledgements', 1);
    }

    public function test_records_survive_account_deletion_and_are_pruned_after_six_months(): void
    {
        app(AcknowledgePersonalDataAction::class)($this->leader, $this->member, PersonalDataSection::ContactDetails);

        $this->member->delete();
        $this->leader->delete();

        $this->assertDatabaseHas('personal_data_acknowledgements', [
            'viewer_name' => $this->leader->name,
            'subject_name' => 'Alex Member',
        ]);

        $this->travel(6)->months();
        $this->travel(1)->days();
        $this->artisan('model:prune', ['--model' => PersonalDataAcknowledgement::class])->assertSuccessful();

        $this->assertDatabaseCount('personal_data_acknowledgements', 0);
    }
}
