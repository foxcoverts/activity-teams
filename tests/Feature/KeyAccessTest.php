<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Keys\RelationManagers\LocksRelationManager as KeyLocksRelationManager;
use App\Filament\Admin\Resources\Locks\LockResource;
use App\Filament\Admin\Resources\Locks\Pages\CreateLock;
use App\Filament\Admin\Resources\Locks\Pages\ViewLock;
use App\Filament\Admin\Resources\Locks\RelationManagers\CoversRelationManager;
use App\Filament\Admin\Resources\Locks\RelationManagers\KeysRelationManager;
use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Filament\Member\Resources\Keys\KeyResource as MemberKeyResource;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager as TeamsAttendeesRelationManager;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamKeyResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Filament\Teams\Resources\Members\RelationManagers\LocksRelationManager;
use App\Models\Activity;
use App\Models\ActivityAudience;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KeyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booking_requires_the_keys_for_its_activity_at_its_venue(): void
    {
        $climbing = Activity::factory()->create(['name' => 'Scout climbing']);
        $shooting = Activity::factory()->create(['name' => 'Shooting']);
        $campsite = Venue::factory()->create(['name' => 'Campsite']);
        $britannia = Venue::factory()->create(['name' => 'Britannia']);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Lock::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $door = Lock::factory()->create(['name' => 'Britannia front door']);
        $guns->covers()->attach($door);

        $warden = Lock::factory()->create(['name' => 'Warden key']);
        $warden->covers()->attach($store);

        $team = Team::factory()->create();
        $team->activities()->sync([$climbing->getKey(), $shooting->getKey()]);
        $team->venues()->sync([$campsite->getKey(), $britannia->getKey()]);

        $atCamp = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);
        $shootingAtCamp = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $shooting->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);
        $shootingAtHq = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $this->assertSame(['Climbing store'], $atCamp->requiredLocks()->pluck('name')->all());
        $this->assertSame(['Britannia shooting store'], $shootingAtCamp->requiredLocks()->pluck('name')->all());
        $this->assertSame(['Britannia shooting store'], $shootingAtHq->requiredLocks()->pluck('name')->all());

        $climber = User::factory()->create();
        Key::factory()->heldBy($climber)->create(['name' => 'Shared climbing'])->locks()->attach($store);
        $this->assertTrue($climber->holdsLock($store));
        $this->assertFalse($climber->holdsLock($guns));

        $wardenHolder = User::factory()->create();
        Key::factory()->heldBy($wardenHolder)->create(['name' => 'Warden'])->locks()->attach($warden);
        $this->assertTrue($wardenHolder->holdsLock($store));
        $this->assertFalse($wardenHolder->holdsLock($guns));

        $doorHolder = User::factory()->create();
        Key::factory()->heldBy($doorHolder)->create(['name' => 'Front door only'])->locks()->attach($door);
        $this->assertFalse($doorHolder->holdsLock($guns));
        $this->assertTrue($doorHolder->holdsLock($door));

        $gunHolder = User::factory()->create();
        Key::factory()->heldBy($gunHolder)->create(['name' => 'Shooting'])->locks()->attach($guns);
        $this->assertTrue($gunHolder->holdsLock($guns));
        $this->assertTrue($gunHolder->holdsLock($door));

        $empty = Booking::factory()->make([
            'activity_id' => null,
            'venue_id' => null,
        ]);
        $this->assertTrue($empty->requiredLocks()->isEmpty());
    }

    public function test_a_system_admin_configures_keys_and_rings_and_a_manager_cannot(): void
    {
        Filament::setCurrentPanel('admin');

        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create(['name' => 'Scout climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $archery = Lock::factory()->create(['name' => 'Archery locker']);

        $this->actingAs($manager);
        $this->get(LockResource::getUrl('create'))->assertForbidden();
        $this->get(AdminKeyResource::getUrl('create'))->assertForbidden();

        $this->actingAs($admin);

        Livewire::test(CreateLock::class)
            ->fillForm([
                'name' => 'Climbing store',
                'requirements' => [
                    [
                        'activity_id' => $activity->getKey(),
                        'venue_id' => $venue->getKey(),
                    ],
                ],
                'covers' => [$archery->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $store = Lock::query()->where('name', 'Climbing store')->first();
        $this->assertNotNull($store);
        $this->assertSame('Scout climbing at Campsite', $store->requirements()->first()?->summary());
        $this->assertTrue($store->covers()->whereKey($archery->getKey())->exists());

        $holder = User::factory()->create(['name' => 'Pat Smith']);

        Livewire::test(CreateKey::class)
            ->fillForm([
                'name' => 'Shared climbing',
                'holder_id' => $holder->getKey(),
                'locks' => [$store->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ring = Key::query()->where('name', 'Shared climbing')->first();
        $this->assertNotNull($ring);
        $this->assertSame($holder->getKey(), $ring->holder_id);
        $this->assertTrue($ring->locks()->whereKey($store->getKey())->exists());

        $this->get(LockResource::getUrl('view', ['record' => $store]))->assertOk();
        $this->get(AdminKeyResource::getUrl('view', ['record' => $ring]))->assertOk();

        Livewire::test(ViewLock::class, ['record' => $store->getKey()])
            ->assertOk()
            ->assertSee('Scout climbing')
            ->assertSee('Campsite')
            ->assertSee('Covers')
            ->assertSee('Keys');

        Livewire::test(CoversRelationManager::class, [
            'ownerRecord' => $store,
            'pageClass' => ViewLock::class,
        ])->assertCanSeeTableRecords([$archery]);

        Livewire::test(KeysRelationManager::class, [
            'ownerRecord' => $store,
            'pageClass' => ViewLock::class,
        ])
            ->assertCanSeeTableRecords([$ring])
            ->assertSee('Pat Smith');

        Livewire::test(ViewKey::class, ['record' => $ring->getKey()])
            ->assertOk()
            ->assertSee('Shared climbing')
            ->assertSee('Pat Smith');

        Livewire::test(KeyLocksRelationManager::class, [
            'ownerRecord' => $ring,
            'pageClass' => ViewKey::class,
        ])
            ->assertSee('Unlocks')
            ->assertCanSeeTableRecords([$store]);
    }

    public function test_a_manager_sees_every_holder_of_the_keys_their_team_needs(): void
    {
        Filament::setCurrentPanel('teams');

        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->grant($manager, MembershipRole::Manager);

        $climbing = Activity::factory()->create(['name' => 'Scout climbing']);
        $shooting = Activity::factory()->create(['name' => 'Shooting']);
        $campsite = Venue::factory()->create(['name' => 'Campsite']);
        $britannia = Venue::factory()->create(['name' => 'Britannia']);
        $team->activities()->sync([$climbing->getKey()]);
        $team->venues()->sync([$campsite->getKey()]);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Lock::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $wardenKey = Lock::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $outsider = User::factory()->create([
            'name' => 'Robin Outsider',
            'phone' => '07000999888',
            'emergency_phone' => '07000111222',
        ]);
        Key::factory()->heldBy($outsider)->create(['name' => 'Shared climbing'])->locks()->attach($store);
        Key::factory()->create(['name' => 'Spare climbing'])->locks()->attach($store);

        $warden = User::factory()->create(['name' => 'Sam Warden']);
        Key::factory()->heldBy($warden)->create(['name' => 'Warden ring'])->locks()->attach($wardenKey);

        Key::factory()->heldBy($outsider)->create(['name' => 'Shooting ring'])->locks()->attach($guns);

        $this->actingAs($manager);

        $this->get(TeamKeyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Shared climbing')
            ->assertSee('Robin Outsider')
            ->assertSee('Spare climbing')
            ->assertSee('In the cupboard')
            ->assertSee('Warden ring')
            ->assertSee('Sam Warden')
            ->assertSee('Climbing store')
            ->assertSee('Warden key')
            ->assertDontSee('Shooting ring')
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('07000999888')
            ->assertDontSee('07000111222')
            ->assertDontSee('New key');
    }

    public function test_a_member_sees_only_the_key_rings_they_hold(): void
    {
        $member = User::factory()->create(['name' => 'Alex Member']);
        $other = User::factory()->create(['name' => 'Blake Other']);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $wardenKey = Lock::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $own = Key::factory()->heldBy($member)->create(['name' => 'My warden ring']);
        $own->locks()->attach($wardenKey);
        Key::factory()->heldBy($other)->create(['name' => 'Someone else']);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $this->get(MemberKeyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Keys')
            ->assertSee('My warden ring')
            ->assertSee('Warden key')
            ->assertSee('These are the keys you hold.')
            ->assertDontSee('Someone else')
            ->assertDontSee('New key');

        $this->get(MemberKeyResource::getUrl('view', ['record' => $own]))
            ->assertOk()
            ->assertSee('Warden key, which also opens Climbing store');

        $this->get(MemberKeyResource::getUrl('view', ['record' => Key::query()->where('name', 'Someone else')->first()]))
            ->assertNotFound();
    }

    public function test_a_booking_shows_its_keys_and_who_holds_a_useful_one(): void
    {
        $manager = User::factory()->teamManager()->create();
        $holder = User::factory()->teamMember()->create(['name' => 'Robin Outsider']);
        $other = User::factory()->teamMember()->create(['name' => 'Alex Guest']);
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($holder->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($other->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        $shared = Key::factory()->heldBy($holder)->create(['name' => 'Shared climbing']);
        $shared->locks()->attach($store);
        Key::factory()->create(['name' => 'Spare climbing']);
        $spare = Key::query()->where('name', 'Spare climbing')->first();
        $spare->locks()->attach($store);
        $door = Lock::factory()->create(['name' => 'Front door']);
        Key::factory()->heldBy($other)->create(['name' => 'Door ring'])->locks()->attach($door);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('keys')
            ->assertActionHasIcon('keys', Heroicon::Key)
            ->assertSee('Key holder attending')
            ->mountAction('keys')
            ->assertMountedActionModalSee('Climbing store')
            ->assertMountedActionModalSee('Attending')
            ->assertMountedActionModalSee('Shared climbing')
            ->assertMountedActionModalSee('Robin Outsider')
            ->assertMountedActionModalSee('In the cupboard')
            ->assertMountedActionModalSee('Spare climbing')
            ->assertMountedActionModalSee('These just need passing to an attendee.');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])->assertSee('Key holder')
            ->assertDontSee('Other keys')
            ->assertSeeHtml('key-holder-badge')
            ->mountTableAction('heldKeys', $holder)
            ->assertMountedActionModalSee('Shared climbing')
            ->assertMountedActionModalSee('Climbing store');

        $keys = view('filament.bookings.person-access-host', [
            'booking' => $booking,
            'person' => $holder,
            'panel' => 'keys',
        ])->render();

        $this->assertStringContainsString('Shared climbing', $keys);
        $this->assertStringContainsString('Climbing store', $keys);
        $this->assertStringNotContainsString('closeAccess', $keys);
        $this->assertStringNotContainsString('toggleOtherQualifications', $keys);

        $empty = Booking::factory()->create();

        Livewire::test(TeamsViewBooking::class, ['record' => $empty->getRouteKey()])
            ->assertActionHidden('keys');
    }

    public function test_a_lead_sees_qualifications_restrictions_and_session_keys(): void
    {
        $lead = User::factory()->teamManager()->create(['name' => 'Lead Instructor', 'phone' => '07000999888']);
        $holder = User::factory()->teamMember()->create(['name' => 'Sam Holder']);
        $member = User::factory()->teamMember()->create(['name' => 'Casey Member']);
        $activity = Activity::factory()->create();
        ActivityAudience::factory()->for($activity)->create()->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
        ]);
        Qualification::factory()->forMember($lead)->scoutPermit()->create([
            'expires_on' => now()->addYear()->toDateString(),
        ]);
        $booking = Booking::factory()->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $permit = Qualification::factory()->forMember($holder)->scoutPermit([
            'restrictions' => 'No night climbing',
        ])->create(['expires_on' => now()->addYear()->toDateString()]);
        Qualification::factory()->forMember($holder)->mountainTraining()->create();
        Qualification::factory()->forMember($holder)->scoutPermit([
            'activity' => 'Archery',
            'category' => 'without Compound Bows',
            'permit_type' => 'supervisory',
        ])->create(['expires_on' => now()->subDay()->toDateString()]);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        Key::factory()->heldBy($holder)->create(['name' => 'Shared climbing'])->locks()->attach($store);
        $unrelated = Lock::factory()->create(['name' => 'Unrelated key']);
        Key::factory()->heldBy($member)->create(['name' => 'Unrelated ring'])->locks()->attach($unrelated);

        foreach ([$lead, $holder, $member] as $person) {
            $booking->attendees()->attach($person->id, [
                'status' => BookingAttendeeStatus::Accepted->value,
                'token' => BookingAttendance::generateToken(),
            ]);
        }

        Filament::setCurrentPanel('member');
        $this->actingAs($lead);

        $this->get(BookingResource::viewUrl($booking))
            ->assertOk()
            ->assertSee('Confirmed booking');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->assertSee('Key holder')
            ->assertSee('Sam Holder')
            ->assertDontSee('07000999888');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->mountTableAction('heldKeys', $holder)
            ->assertMountedActionModalSee('Shared climbing')
            ->assertMountedActionModalSee('Climbing store');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->mountTableAction('qualifications', $holder)
            ->assertMountedActionModalSee('Climbing and Abseiling — Artificial Top Rope — Leadership');

        $this->actingAs($member);

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->mountTableAction('heldKeys', $holder)
            ->assertTableActionNotMounted('heldKeys');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->mountTableAction('qualifications', $holder)
            ->assertTableActionNotMounted('qualifications');

        Filament::setCurrentPanel('teams');
        $this->actingAs($lead);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHasIcon('keys', Heroicon::Key)
            ->mountAction('keys')
            ->assertMountedActionModalSee('Attending')
            ->assertMountedActionModalSee('Shared climbing')
            ->assertMountedActionModalSee('Sam Holder');

        $qualifications = view('filament.bookings.person-access-host', [
            'booking' => $booking,
            'person' => $holder,
            'panel' => 'qualifications',
        ])->render();

        $this->assertStringContainsString('Show other qualifications', $qualifications);
        $this->assertStringContainsString('Climbing Wall Instructor', $qualifications);
        $this->assertStringContainsString('No night climbing', $qualifications);
        $this->assertStringContainsString('text-success-600', $qualifications);
        $this->assertStringContainsString('text-danger-600', $qualifications);
        $this->assertStringContainsString('x-on:click="showOther = ! showOther"', $qualifications);
        $this->assertStringNotContainsString('toggleOtherQualifications', $qualifications);

        $this->actingAs($member);

        $this->get(BookingResource::viewUrl($booking))
            ->assertOk()
            ->assertDontSee('Key holder')
            ->assertDontSee('No night climbing')
            ->assertDontSee('Shared climbing');
    }

    public function test_the_members_list_shows_and_filters_the_keys_the_team_needs(): void
    {
        Filament::setCurrentPanel('teams');

        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->grant($manager, MembershipRole::Manager);

        $climbing = Activity::factory()->create(['name' => 'Scout climbing']);
        $shooting = Activity::factory()->create(['name' => 'Shooting']);
        $campsite = Venue::factory()->create(['name' => 'Campsite']);
        $britannia = Venue::factory()->create(['name' => 'Britannia']);
        $team->activities()->sync([$climbing->getKey()]);
        $team->venues()->sync([$campsite->getKey()]);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Lock::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $wardenKey = Lock::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $climber = User::factory()->create(['name' => 'Pat Climber']);
        $team->grant($climber, MembershipRole::Member);
        Key::factory()->heldBy($climber)->create(['name' => 'Shared climbing'])->locks()->attach($store);

        $warden = User::factory()->create(['name' => 'Sam Warden']);
        $team->grant($warden, MembershipRole::Member);
        Key::factory()->heldBy($warden)->create(['name' => 'Warden ring'])->locks()->attach($wardenKey);

        $shooter = User::factory()->create(['name' => 'Alex Shooter']);
        $team->grant($shooter, MembershipRole::Member);
        Key::factory()->heldBy($shooter)->create(['name' => 'Shooting ring'])->locks()->attach($guns);

        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$climber, $warden, $shooter])
            ->assertTableColumnStateSet('keys', 'Key holder', $climber)
            ->assertTableColumnStateSet('keys', 'Key holder', $warden)
            ->assertTableColumnStateSet('keys', null, $shooter)
            ->assertDontSee('Britannia shooting store')
            ->mountAction(TestAction::make('heldKeys')->table($climber))
            ->assertMountedActionModalSee('Climbing store')
            ->assertMountedActionModalSee('Scout climbing at Campsite')
            ->assertMountedActionModalSee('Pat Climber');

        Livewire::test(ListMembers::class)
            ->assertActionHidden(TestAction::make('heldKeys')->table($shooter))
            ->filterTable('key', $store->getKey())
            ->assertCanSeeTableRecords([$climber, $warden])
            ->assertCanNotSeeTableRecords([$shooter]);

        Livewire::test(LocksRelationManager::class, [
            'ownerRecord' => $climber,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords([$store])
            ->assertTableColumnStateSet('required_for', ['Scout climbing at Campsite'], $store)
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('Warden key');

        Livewire::test(LocksRelationManager::class, [
            'ownerRecord' => $warden,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords([$store])
            ->assertTableColumnStateSet('required_for', ['Scout climbing at Campsite'], $store)
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('Warden key');

        Livewire::test(LocksRelationManager::class, [
            'ownerRecord' => $shooter,
            'pageClass' => ViewMember::class,
        ])->assertForbidden();
    }

    public function test_the_keys_action_is_amber_when_no_accepted_holder_is_attending(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $declined = User::factory()->teamMember($team)->create(['name' => 'Dana Declined']);
        $maybe = User::factory()->teamMember($team)->create(['name' => 'Morgan Maybe']);
        $uninvited = User::factory()->teamMember($team)->create(['name' => 'Uma Uninvited']);
        $booking = Booking::factory()->forTeam($team)->create();

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        Key::factory()->heldBy($declined)->create(['name' => 'Declined ring'])->locks()->attach($store);
        Key::factory()->heldBy($maybe)->create(['name' => 'Maybe ring'])->locks()->attach($store);
        Key::factory()->heldBy($uninvited)->create(['name' => 'Uninvited ring'])->locks()->attach($store);
        Key::factory()->create(['name' => 'Cupboard ring'])->locks()->attach($store);

        $booking->attendees()->attach($declined->id, [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($maybe->id, [
            'status' => BookingAttendeeStatus::Tentative->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $memberUrl = MemberResource::getUrl('view', ['record' => $uninvited], panel: 'teams', tenant: $team);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('keys')
            ->assertActionHasIcon('keys', Heroicon::OutlinedKey)
            ->assertSee('No key holder attending')
            ->mountAction('keys')
            ->assertMountedActionModalSee('Climbing store')
            ->assertMountedActionModalSee('Cannot make it')
            ->assertMountedActionModalSee('Declined ring')
            ->assertMountedActionModalSee('Dana Declined')
            ->assertMountedActionModalSee('Invited')
            ->assertMountedActionModalSee('Maybe ring')
            ->assertMountedActionModalSee('Morgan Maybe')
            ->assertMountedActionModalSee('Not invited')
            ->assertMountedActionModalSee('Uninvited ring')
            ->assertMountedActionModalSee('Uma Uninvited')
            ->assertMountedActionModalSee('In the cupboard')
            ->assertMountedActionModalSee('Cupboard ring')
            ->assertMountedActionModalSeeHtml($memberUrl)
            ->assertDontSee('Attending');

        $html = view('filament.bookings.keys', [
            'keys' => BookingKeysAction::lines($booking),
        ])->render();

        $invited = strpos($html, 'Maybe ring');
        $cupboard = strpos($html, 'Cupboard ring');
        $notInvited = strpos($html, 'Uninvited ring');
        $cannotMakeIt = strpos($html, 'Declined ring');

        $this->assertNotFalse($invited);
        $this->assertNotFalse($cupboard);
        $this->assertNotFalse($notInvited);
        $this->assertNotFalse($cannotMakeIt);
        $this->assertLessThan($cupboard, $invited);
        $this->assertLessThan($notInvited, $cupboard);
        $this->assertLessThan($cannotMakeIt, $notInvited);
    }

    public function test_the_keys_action_updates_when_a_holder_is_marked_attending(): void
    {
        $manager = User::factory()->teamManager()->create();
        $holder = User::factory()->teamMember()->create(['name' => 'Robin Outsider']);
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($holder->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $store = Lock::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        Key::factory()->heldBy($holder)->create(['name' => 'Shared climbing'])->locks()->attach($store);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $page = Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHasIcon('keys', Heroicon::OutlinedKey)
            ->assertSee('No key holder attending');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->callTableAction('editAttendance', $holder, [
                'response' => BookingAttendeeResponse::Yes->value,
            ])
            ->assertDispatched('booking-attendance-updated');

        $page->dispatch('booking-attendance-updated')
            ->assertActionHasIcon('keys', Heroicon::Key)
            ->assertSee('Key holder attending')
            ->assertDontSee('No key holder attending');

        Livewire::test(TeamsAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->callTableAction('editAttendance', $holder, [
                'response' => BookingAttendeeResponse::No->value,
            ])
            ->assertDispatched('booking-attendance-updated');

        $page->dispatch('booking-attendance-updated')
            ->assertActionHasIcon('keys', Heroicon::OutlinedKey)
            ->assertSee('No key holder attending');
    }
}
