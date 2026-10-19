<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\KeyRings\KeyRingResource as ManageKeyRingResource;
use App\Filament\Manage\Resources\KeyRings\Pages\CreateKeyRing;
use App\Filament\Manage\Resources\Keys\KeyResource;
use App\Filament\Manage\Resources\Keys\Pages\CreateKey;
use App\Filament\Manage\Resources\TeamKeys\TeamKeyResource;
use App\Filament\Team\Resources\KeyRings\KeyRingResource as TeamKeyRingResource;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Key;
use App\Models\KeyRing;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
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
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $admin = User::factory()->teamLeader()->create();
        $manager = User::factory()->manager()->create();
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

        $this->get(KeyResource::getUrl('view', ['record' => $store]))
            ->assertOk()
            ->assertSee('Shared climbing')
            ->assertSee('Pat Smith')
            ->assertSee('Scout climbing')
            ->assertSee('Archery locker');
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

        $this->get(TeamKeyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Climbing store')
            ->assertSee('Scout climbing at Campsite')
            ->assertSee('Shared climbing: Robin Outsider')
            ->assertSee('Spare climbing: In the cupboard')
            ->assertSee('Warden ring: Sam Warden')
            ->assertDontSee('Britannia shooting store')
            ->assertDontSee('07000999888')
            ->assertDontSee('07000111222')
            ->assertDontSee('New key');
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

        Filament::setCurrentPanel(Filament::getPanel('team'));
        $this->actingAs($member);

        $this->get(TeamKeyRingResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Key rings')
            ->assertSee('My warden ring')
            ->assertSee('Warden key')
            ->assertSee('These are the key rings you hold.')
            ->assertDontSee('Someone else')
            ->assertDontSee('New key ring');

        $this->get(TeamKeyRingResource::getUrl('view', ['record' => $own]))
            ->assertOk()
            ->assertSee('Warden key, which also opens Climbing store');

        $this->get(TeamKeyRingResource::getUrl('view', ['record' => KeyRing::query()->where('name', 'Someone else')->first()]))
            ->assertNotFound();
    }
}
