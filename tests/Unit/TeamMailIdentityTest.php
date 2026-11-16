<?php

namespace Tests\Unit;

use App\Models\Team;
use App\Support\InboundMailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamMailIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_mail_from_helpers_use_custom_values_and_defaults(): void
    {
        config(['mail.from.address' => 'hello@example.test']);

        $team = Team::factory()->create([
            'name' => 'Climbing Club',
            'slug' => 'climbing',
            'mail_from_name' => 'Fox Coverts Climbing',
            'mail_from_local' => 'club.climbing',
        ]);

        $this->assertSame('Fox Coverts Climbing', $team->mailFromName());
        $this->assertSame('club.climbing', $team->mailFromLocal());
        $this->assertSame('club.climbing@example.test', $team->mailFromAddress());

        $blankLocal = Team::factory()->create([
            'name' => 'Archery Squad',
            'slug' => 'archery',
            'mail_from_local' => null,
        ]);

        $this->assertSame('team+archery', $blankLocal->defaultMailFromLocal());
        $this->assertSame('Archery Squad', $blankLocal->mailFromName());

        $provisional = new Team(['name' => 'River Canoe', 'slug' => null]);

        $this->assertSame('team+river-canoe', $provisional->defaultMailFromLocal());
    }

    public function test_inbound_team_address_uses_slug_and_parses_legacy_ulid(): void
    {
        $team = Team::factory()->create(['slug' => 'climbing']);

        $address = InboundMailAddress::forTeam($team);

        $this->assertSame('team+climbing@'.InboundMailAddress::domain(), $address);
        $this->assertTrue($team->is(InboundMailAddress::findResource($address)));

        $legacy = InboundMailAddress::make(InboundMailAddress::LEGACY_TEAMS, $team->getKey());

        $this->assertTrue($team->is(InboundMailAddress::findResource($legacy)));
    }

    public function test_inbound_team_address_falls_back_to_id_without_slug(): void
    {
        $team = Team::factory()->create();
        $team->forceFill(['slug' => ''])->syncOriginal();

        $address = InboundMailAddress::forTeam($team);

        $this->assertSame('team+'.$team->getKey().'@'.InboundMailAddress::domain(), $address);
        $this->assertTrue($team->is(InboundMailAddress::findResource($address)));
    }
}
