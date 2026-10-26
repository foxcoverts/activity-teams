<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\KeyRings\KeyRingResource as ManageKeyRingResource;
use App\Filament\Admin\Resources\KeyRings\Pages\CreateKeyRing;
use App\Filament\Admin\Resources\KeyRings\Pages\ViewKeyRing;
use App\Filament\Admin\Resources\KeyRings\RelationManagers\KeysRelationManager as KeyRingKeysRelationManager;
use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Keys\Pages\CreateKey;
use App\Filament\Admin\Resources\Keys\Pages\ViewKey;
use App\Filament\Admin\Resources\Keys\RelationManagers\CoversRelationManager;
use App\Filament\Admin\Resources\Keys\RelationManagers\RingsRelationManager;
use App\Filament\Bookings\BookingKeysAction;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Manage\Resources\KeyRings\KeyRingResource as TeamKeyRingResource;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Filament\Manage\Resources\Members\RelationManagers\KeysRelationManager;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Filament\Member\Resources\Bookings\RelationManagers\AttendeesRelationManager as MemberAttendeesRelationManager;
use App\Filament\Member\Resources\KeyRings\KeyRingResource as MemberKeyRingResource;
use App\Livewire\SessionRoster;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Key;
use App\Models\KeyRing;
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

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Key::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $door = Key::factory()->create(['name' => 'Britannia front door']);
        $guns->covers()->attach($door);

        $warden = Key::factory()->create(['name' => 'Warden key']);
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

        $this->assertSame(['Climbing store'], $atCamp->requiredKeys()->pluck('name')->all());
        $this->assertSame(['Britannia shooting store'], $shootingAtCamp->requiredKeys()->pluck('name')->all());
        $this->assertSame(['Britannia shooting store'], $shootingAtHq->requiredKeys()->pluck('name')->all());

        $climber = User::factory()->create();
        KeyRing::factory()->heldBy($climber)->create(['name' => 'Shared climbing'])->keys()->attach($store);
        $this->assertTrue($climber->holdsKey($store));
        $this->assertFalse($climber->holdsKey($guns));

        $wardenHolder = User::factory()->create();
        KeyRing::factory()->heldBy($wardenHolder)->create(['name' => 'Warden'])->keys()->attach($warden);
        $this->assertTrue($wardenHolder->holdsKey($store));
        $this->assertFalse($wardenHolder->holdsKey($guns));

        $doorHolder = User::factory()->create();
        KeyRing::factory()->heldBy($doorHolder)->create(['name' => 'Front door only'])->keys()->attach($door);
        $this->assertFalse($doorHolder->holdsKey($guns));
        $this->assertTrue($doorHolder->holdsKey($door));

        $gunHolder = User::factory()->create();
        KeyRing::factory()->heldBy($gunHolder)->create(['name' => 'Shooting'])->keys()->attach($guns);
        $this->assertTrue($gunHolder->holdsKey($guns));
        $this->assertTrue($gunHolder->holdsKey($door));

        $empty = Booking::factory()->make([
            'activity_id' => null,
            'venue_id' => null,
        ]);
        $this->assertTrue($empty->requiredKeys()->isEmpty());
    }

    public function test_a_system_admin_configures_keys_and_rings_and_a_manager_cannot(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $activity = Activity::factory()->create(['name' => 'Scout climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $archery = Key::factory()->create(['name' => 'Archery locker']);

        $this->actingAs($manager);
        $this->get(KeyResource::getUrl('create'))->assertForbidden();
        $this->get(ManageKeyRingResource::getUrl('create'))->assertForbidden();

        $this->actingAs($admin);

        Livewire::test(CreateKey::class)
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

        $store = Key::query()->where('name', 'Climbing store')->first();
        $this->assertNotNull($store);
        $this->assertSame('Scout climbing at Campsite', $store->requirements()->first()?->summary());
        $this->assertTrue($store->covers()->whereKey($archery->getKey())->exists());

        $holder = User::factory()->create(['name' => 'Pat Smith']);

        Livewire::test(CreateKeyRing::class)
            ->fillForm([
                'name' => 'Shared climbing',
                'holder_id' => $holder->getKey(),
                'keys' => [$store->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ring = KeyRing::query()->where('name', 'Shared climbing')->first();
        $this->assertNotNull($ring);
        $this->assertSame($holder->getKey(), $ring->holder_id);
        $this->assertTrue($ring->keys()->whereKey($store->getKey())->exists());

        $this->get(KeyResource::getUrl('view', ['record' => $store]))->assertOk();
        $this->get(ManageKeyRingResource::getUrl('view', ['record' => $ring]))->assertOk();

        Livewire::test(ViewKey::class, ['record' => $store->getKey()])
            ->assertOk()
            ->assertSee('Scout climbing')
            ->assertSee('Campsite')
            ->assertSee('Covers')
            ->assertSee('Key Rings');

        Livewire::test(CoversRelationManager::class, [
            'ownerRecord' => $store,
            'pageClass' => ViewKey::class,
        ])->assertCanSeeTableRecords([$archery]);

        Livewire::test(RingsRelationManager::class, [
            'ownerRecord' => $store,
            'pageClass' => ViewKey::class,
        ])
            ->assertCanSeeTableRecords([$ring])
            ->assertSee('Pat Smith');

        Livewire::test(ViewKeyRing::class, ['record' => $ring->getKey()])
            ->assertOk()
            ->assertSee('Shared climbing')
            ->assertSee('Pat Smith')
            ->assertSee('Keys');

        Livewire::test(KeyRingKeysRelationManager::class, [
            'ownerRecord' => $ring,
            'pageClass' => ViewKeyRing::class,
        ])->assertCanSeeTableRecords([$store]);
    }

    public function test_a_manager_sees_every_holder_of_the_keys_their_team_needs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->grant($manager, MembershipRole::Manager);

        $climbing = Activity::factory()->create(['name' => 'Scout climbing']);
        $shooting = Activity::factory()->create(['name' => 'Shooting']);
        $campsite = Venue::factory()->create(['name' => 'Campsite']);
        $britannia = Venue::factory()->create(['name' => 'Britannia']);
        $team->activities()->sync([$climbing->getKey()]);
        $team->venues()->sync([$campsite->getKey()]);

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Key::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $wardenKey = Key::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $outsider = User::factory()->create([
            'name' => 'Robin Outsider',
            'phone' => '07000999888',
            'emergency_phone' => '07000111222',
        ]);
        KeyRing::factory()->heldBy($outsider)->create(['name' => 'Shared climbing'])->keys()->attach($store);
        KeyRing::factory()->create(['name' => 'Spare climbing'])->keys()->attach($store);

        $warden = User::factory()->create(['name' => 'Sam Warden']);
        KeyRing::factory()->heldBy($warden)->create(['name' => 'Warden ring'])->keys()->attach($wardenKey);

        KeyRing::factory()->heldBy($outsider)->create(['name' => 'Shooting ring'])->keys()->attach($guns);

        $this->actingAs($manager);

        $this->get(TeamKeyRingResource::getUrl('index'))
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
            ->assertDontSee('New key ring');
    }

    public function test_a_member_sees_only_the_key_rings_they_hold(): void
    {
        $member = User::factory()->create(['name' => 'Alex Member']);
        $other = User::factory()->create(['name' => 'Blake Other']);

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $wardenKey = Key::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $own = KeyRing::factory()->heldBy($member)->create(['name' => 'My warden ring']);
        $own->keys()->attach($wardenKey);
        KeyRing::factory()->heldBy($other)->create(['name' => 'Someone else']);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $this->get(MemberKeyRingResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Key Rings')
            ->assertSee('My warden ring')
            ->assertSee('Warden key')
            ->assertSee('These are the key rings you hold.')
            ->assertDontSee('Someone else')
            ->assertDontSee('New key ring');

        $this->get(MemberKeyRingResource::getUrl('view', ['record' => $own]))
            ->assertOk()
            ->assertSee('Warden key, which also opens Climbing store');

        $this->get(MemberKeyRingResource::getUrl('view', ['record' => KeyRing::query()->where('name', 'Someone else')->first()]))
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

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        $shared = KeyRing::factory()->heldBy($holder)->create(['name' => 'Shared climbing']);
        $shared->keys()->attach($store);
        KeyRing::factory()->create(['name' => 'Spare climbing']);
        $spare = KeyRing::query()->where('name', 'Spare climbing')->first();
        $spare->keys()->attach($store);
        $door = Key::factory()->create(['name' => 'Front door']);
        KeyRing::factory()->heldBy($other)->create(['name' => 'Door ring'])->keys()->attach($door);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
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

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ManageViewBooking::class,
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

        Livewire::test(ManageViewBooking::class, ['record' => $empty->getRouteKey()])
            ->assertActionHidden('keys');
    }

    public function test_a_lead_sees_qualifications_restrictions_and_session_keys(): void
    {
        $lead = User::factory()->teamManager()->create(['name' => 'Lead Instructor', 'phone' => '07000999888']);
        $holder = User::factory()->teamMember()->create(['name' => 'Sam Holder']);
        $member = User::factory()->teamMember()->create(['name' => 'Casey Member']);
        $activity = Activity::factory()->create();
        $activity->qualificationRequirements()->create([
            'kind' => 'scout_permit',
            'criteria' => [
                'activity' => 'Climbing and Abseiling',
                'permit_type' => 'leadership',
            ],
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

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        KeyRing::factory()->heldBy($holder)->create(['name' => 'Shared climbing'])->keys()->attach($store);
        $unrelated = Key::factory()->create(['name' => 'Unrelated key']);
        KeyRing::factory()->heldBy($member)->create(['name' => 'Unrelated ring'])->keys()->attach($unrelated);

        foreach ([$lead, $holder, $member] as $person) {
            $booking->attendees()->attach($person->id, [
                'status' => BookingAttendeeStatus::Accepted->value,
                'token' => BookingAttendance::generateToken(),
            ]);
        }

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($lead);

        $this->get(BookingResource::getUrl('view', ['record' => $booking]))
            ->assertOk()
            ->assertSee('Confirmed booking');

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])
            ->assertSee('Key holder')
            ->assertSee('Sam Holder')
            ->assertDontSee('07000999888');

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])
            ->mountTableAction('heldKeys', $holder)
            ->assertMountedActionModalSee('Shared climbing')
            ->assertMountedActionModalSee('Climbing store');

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])
            ->mountTableAction('qualifications', $holder)
            ->assertMountedActionModalSee('Climbing and Abseiling — Artificial Top Rope — Leadership');

        $this->actingAs($member);

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])
            ->mountTableAction('heldKeys', $holder)
            ->assertTableActionNotMounted('heldKeys');

        Livewire::test(MemberAttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => MemberViewBooking::class,
        ])
            ->mountTableAction('qualifications', $holder)
            ->assertTableActionNotMounted('qualifications');

        $this->actingAs($lead);

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()])
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

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertSee('Qualified')
            ->assertDontSee('Other keys')
            ->call('openKeys', $member->getKey())
            ->assertForbidden();

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertSee('Qualified')
            ->call('openQualifications', $holder->getKey())
            ->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership')
            ->assertDontSee('Climbing Wall Instructor')
            ->assertDontSee('Archery')
            ->call('toggleOtherQualifications')
            ->assertSee('Climbing Wall Instructor')
            ->assertSee('Archery')
            ->call('openQualification', $permit->getKey())
            ->assertSee('No night climbing')
            ->assertSee('Restrictions')
            ->tap(function ($roster): void {
                $this->assertSame(1, substr_count($roster->html(), 'wire:click="closeQualification"'));
            })
            ->call('closeQualification')
            ->assertDontSee('No night climbing')
            ->assertSee('Climbing and Abseiling — Artificial Top Rope — Leadership');

        $this->actingAs($member);

        $this->get(BookingResource::getUrl('view', ['record' => $booking]))
            ->assertOk()
            ->assertDontSee('Key holder')
            ->assertDontSee('No night climbing')
            ->assertDontSee('Shared climbing');

        Livewire::test(SessionRoster::class, ['booking' => $booking])
            ->assertSee('Sam Holder')
            ->assertDontSee('Key holder')
            ->call('openKeys', $holder->getKey())
            ->assertForbidden();
    }

    public function test_the_members_list_shows_and_filters_the_keys_the_team_needs(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $team = Team::factory()->create();
        $manager = User::factory()->create();
        $team->grant($manager, MembershipRole::Manager);

        $climbing = Activity::factory()->create(['name' => 'Scout climbing']);
        $shooting = Activity::factory()->create(['name' => 'Shooting']);
        $campsite = Venue::factory()->create(['name' => 'Campsite']);
        $britannia = Venue::factory()->create(['name' => 'Britannia']);
        $team->activities()->sync([$climbing->getKey()]);
        $team->venues()->sync([$campsite->getKey()]);

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $climbing->getKey(),
            'venue_id' => $campsite->getKey(),
        ]);

        $guns = Key::factory()->create(['name' => 'Britannia shooting store']);
        $guns->requirements()->create([
            'activity_id' => $shooting->getKey(),
            'venue_id' => $britannia->getKey(),
        ]);

        $wardenKey = Key::factory()->create(['name' => 'Warden key']);
        $wardenKey->covers()->attach($store);

        $climber = User::factory()->create(['name' => 'Pat Climber']);
        $team->grant($climber, MembershipRole::Member);
        KeyRing::factory()->heldBy($climber)->create(['name' => 'Shared climbing'])->keys()->attach($store);

        $warden = User::factory()->create(['name' => 'Sam Warden']);
        $team->grant($warden, MembershipRole::Member);
        KeyRing::factory()->heldBy($warden)->create(['name' => 'Warden ring'])->keys()->attach($wardenKey);

        $shooter = User::factory()->create(['name' => 'Alex Shooter']);
        $team->grant($shooter, MembershipRole::Member);
        KeyRing::factory()->heldBy($shooter)->create(['name' => 'Shooting ring'])->keys()->attach($guns);

        $this->actingAs($manager);
        Filament::setTenant($team);

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

        Livewire::test(KeysRelationManager::class, [
            'ownerRecord' => $climber,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords([$store])
            ->assertTableColumnStateSet('required_for', ['Scout climbing at Campsite'], $store)
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('Warden key');

        Livewire::test(KeysRelationManager::class, [
            'ownerRecord' => $warden,
            'pageClass' => ViewMember::class,
        ])
            ->assertCanSeeTableRecords([$store])
            ->assertTableColumnStateSet('required_for', ['Scout climbing at Campsite'], $store)
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('Warden key');

        Livewire::test(KeysRelationManager::class, [
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

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        KeyRing::factory()->heldBy($declined)->create(['name' => 'Declined ring'])->keys()->attach($store);
        KeyRing::factory()->heldBy($maybe)->create(['name' => 'Maybe ring'])->keys()->attach($store);
        KeyRing::factory()->heldBy($uninvited)->create(['name' => 'Uninvited ring'])->keys()->attach($store);
        KeyRing::factory()->create(['name' => 'Cupboard ring'])->keys()->attach($store);

        $booking->attendees()->attach($declined->id, [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->attendees()->attach($maybe->id, [
            'status' => BookingAttendeeStatus::Tentative->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);

        $memberUrl = MemberResource::getUrl('view', ['record' => $uninvited], tenant: $team);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
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

        $store = Key::factory()->create(['name' => 'Climbing store']);
        $store->requirements()->create([
            'activity_id' => $booking->activity_id,
            'venue_id' => $booking->venue_id,
        ]);
        KeyRing::factory()->heldBy($holder)->create(['name' => 'Shared climbing'])->keys()->attach($store);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);

        $page = Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHasIcon('keys', Heroicon::OutlinedKey)
            ->assertSee('No key holder attending');

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ManageViewBooking::class,
        ])
            ->callTableAction('editAttendance', $holder, [
                'response' => BookingAttendeeResponse::Yes->value,
            ])
            ->assertDispatched('booking-attendance-updated');

        $page->dispatch('booking-attendance-updated')
            ->assertActionHasIcon('keys', Heroicon::Key)
            ->assertSee('Key holder attending')
            ->assertDontSee('No key holder attending');

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ManageViewBooking::class,
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
