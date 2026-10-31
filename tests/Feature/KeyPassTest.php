<?php

namespace Tests\Feature;

use App\Actions\AcceptTeamInvitationAction;
use App\Actions\PassKeyAction;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Keys\KeyResource as ManageKeyResource;
use App\Filament\Manage\Resources\Keys\Pages\ListKeys as ManageListKeys;
use App\Filament\Member\Resources\Keys\KeyResource as MemberKeyResource;
use App\Filament\Member\Resources\Keys\Pages\ListKeys as MemberListKeys;
use App\Models\Activity;
use App\Models\Lock;
use App\Models\Key;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\KeyPassedFromNotification;
use App\Support\InboundMailAddress;
use App\Notifications\KeyPassedToNotification;
use App\Notifications\KeyReturnedToCupboardNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class KeyPassTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_holder_passes_a_ring_to_a_member_and_only_the_new_holder_is_emailed(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Pat Holder']);
        $receiver = User::factory()->teamMember($team)->create(['name' => 'Alex Receiver']);
        $ring = $this->heldRing($holder, $key, 'Shared climbing');

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($holder);

        Livewire::test(MemberListKeys::class)
            ->callAction(TestAction::make('pass')->table($ring), [
                'holder_id' => $receiver->getKey(),
            ]);

        $ring->refresh();

        $this->assertSame($receiver->getKey(), $ring->holder_id);
        $this->assertTrue($ring->locks()->whereKey($key->getKey())->exists());
        Notification::assertSentTo($receiver, KeyPassedToNotification::class);
        Notification::assertNotSentTo($holder, KeyPassedToNotification::class);
        Notification::assertNotSentTo($holder, KeyPassedFromNotification::class);
        Notification::assertCount(1);

        $this->get(MemberKeyResource::getUrl('index'))
            ->assertOk()
            ->assertDontSee('Shared climbing');

        $this->actingAs($receiver);
        $this->get(MemberKeyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Shared climbing');
    }

    public function test_a_manager_passing_a_held_ring_emails_the_holders_and_not_the_actor(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $manager = User::factory()->teamManager($team)->create(['name' => 'Casey Manager']);
        $holder = User::factory()->teamMember($team)->create(['name' => 'Pat Holder']);
        $receiver = User::factory()->teamMember($team)->create(['name' => 'Alex Receiver']);
        $ring = $this->heldRing($holder, $key);

        (new PassKeyAction)($manager, $ring, $receiver->getKey());

        $this->assertSame($receiver->getKey(), $ring->fresh()->holder_id);
        Notification::assertSentTo($receiver, KeyPassedToNotification::class);
        Notification::assertSentTo($holder, KeyPassedFromNotification::class);
        Notification::assertNotSentTo($manager, KeyPassedToNotification::class);
        Notification::assertNotSentTo($manager, KeyPassedFromNotification::class);
        Notification::assertCount(2);
    }

    public function test_passing_to_the_cupboard_emails_the_previous_holder_and_other_managers_once(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $actor = User::factory()->teamManager($team)->create(['name' => 'Casey Manager']);
        $otherManager = User::factory()->teamManager($team)->create(['name' => 'Riley Manager']);
        $holder = User::factory()->teamMember($team)->create(['name' => 'Pat Holder']);
        $ring = $this->heldRing($holder, $key);

        (new PassKeyAction)($actor, $ring, null);

        $this->assertNull($ring->fresh()->holder_id);
        Notification::assertSentTo($holder, KeyPassedFromNotification::class);
        Notification::assertNotSentTo($holder, KeyReturnedToCupboardNotification::class);
        Notification::assertSentTo($otherManager, KeyReturnedToCupboardNotification::class);
        Notification::assertNotSentTo($actor, KeyReturnedToCupboardNotification::class);
        Notification::assertNotSentTo($actor, KeyPassedFromNotification::class);
        Notification::assertCount(2);
    }

    public function test_a_holder_passing_to_the_cupboard_skips_their_own_mail(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamManager($team)->create(['name' => 'Pat Holder']);
        $otherManager = User::factory()->teamManager($team)->create(['name' => 'Riley Manager']);
        $ring = $this->heldRing($holder, $key);

        (new PassKeyAction)($holder, $ring, null);

        $this->assertNull($ring->fresh()->holder_id);
        Notification::assertNotSentTo($holder, KeyPassedFromNotification::class);
        Notification::assertNotSentTo($holder, KeyReturnedToCupboardNotification::class);
        Notification::assertSentTo($otherManager, KeyReturnedToCupboardNotification::class);
        Notification::assertCount(1);
    }

    public function test_a_holder_cannot_pass_to_a_member_of_a_team_that_does_not_need_the_keys(): void
    {
        [$team, $key] = $this->climbingTeam();
        $other = Team::factory()->create();
        $holder = User::factory()->teamMember($team)->create();
        $outsider = User::factory()->teamMember($other)->create();
        $ring = $this->heldRing($holder, $key);

        try {
            (new PassKeyAction)($holder, $ring, $outsider->getKey());
            $this->fail('Passed the ring to a member of an unrelated team.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('Choose a member who can hold this key.'),
                $exception->validator->errors()->first('holder_id'),
            );
        }

        $this->assertSame($holder->getKey(), $ring->fresh()->holder_id);
    }

    public function test_a_member_cannot_pass_someone_elses_ring(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);

        $this->expectException(AuthorizationException::class);

        (new PassKeyAction)($member, $ring, $receiver->getKey());
    }

    public function test_a_manager_can_pass_a_cupboard_ring_and_an_outsider_held_ring(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $manager = User::factory()->teamManager($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $outsider = User::factory()->create(['name' => 'Robin Outsider']);
        $cupboard = $this->heldRing(null, $key, 'Spare climbing');
        $held = $this->heldRing($outsider, $key, 'Shared climbing');

        (new PassKeyAction)($manager, $cupboard, $receiver->getKey());
        (new PassKeyAction)($manager, $held, $receiver->getKey());

        $this->assertSame($receiver->getKey(), $cupboard->fresh()->holder_id);
        $this->assertSame($receiver->getKey(), $held->fresh()->holder_id);
        Notification::assertSentTo($receiver, KeyPassedToNotification::class);
        Notification::assertSentTo($outsider, KeyPassedFromNotification::class);
        Notification::assertNotSentTo($manager, KeyPassedToNotification::class);
    }

    public function test_a_manager_of_an_unrelated_team_cannot_pass_the_ring(): void
    {
        [$team, $key] = $this->climbingTeam();
        $other = Team::factory()->create();
        $manager = User::factory()->teamManager($other)->create();
        $holder = User::factory()->teamMember($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);

        $this->expectException(AuthorizationException::class);

        (new PassKeyAction)($manager, $ring, $receiver->getKey());
    }

    public function test_a_suspended_person_is_not_a_valid_recipient(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $suspended = User::factory()->create(['suspended' => true, 'name' => 'Sam Suspended']);
        $team->grant($suspended, MembershipRole::Member);
        $ring = $this->heldRing($holder, $key);

        try {
            (new PassKeyAction)($holder, $ring, $suspended->getKey());
            $this->fail('Passed the ring to a suspended member.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('Choose a member who can hold this key.'),
                $exception->validator->errors()->first('holder_id'),
            );
        }
    }

    public function test_a_manager_can_pass_to_and_from_a_declined_placeholder_without_emailing_them(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $manager = User::factory()->teamManager($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $placeholder = User::factory()->placeholder()->create(['name' => 'Sam Placeholder']);
        $team->grant($placeholder, MembershipRole::Member);
        TeamInvitation::factory()->declined()->create([
            'team_id' => $team->getKey(),
            'user_id' => $placeholder->getKey(),
        ]);
        $ring = $this->heldRing($receiver, $key);

        (new PassKeyAction)($manager, $ring, $placeholder->getKey());

        $this->assertSame($placeholder->getKey(), $ring->fresh()->holder_id);
        Notification::assertNotSentTo($placeholder, KeyPassedToNotification::class);
        Notification::assertSentTo($receiver, KeyPassedFromNotification::class);

        Notification::fake();

        (new PassKeyAction)($manager, $ring->fresh(), $receiver->getKey());

        $this->assertSame($receiver->getKey(), $ring->fresh()->holder_id);
        Notification::assertSentTo($receiver, KeyPassedToNotification::class);
        Notification::assertNotSentTo($placeholder, KeyPassedFromNotification::class);
    }

    public function test_adopting_a_placeholder_moves_their_rings_without_pass_mail(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $placeholder = User::factory()->placeholder()->create(['name' => 'Sam Placeholder']);
        $team->grant($placeholder, MembershipRole::Member);
        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->getKey(),
            'user_id' => $placeholder->getKey(),
        ]);
        $ring = $this->heldRing($placeholder, $key);
        $account = User::factory()->create();

        app(AcceptTeamInvitationAction::class)->claimWith($invitation, $account);

        $this->assertSame($account->getKey(), $ring->fresh()->holder_id);
        Notification::assertNothingSent();
    }

    public function test_a_suspended_previous_holder_is_not_emailed(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $manager = User::factory()->teamManager($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $holder = User::factory()->create(['suspended' => true, 'name' => 'Sam Suspended']);
        $team->grant($holder, MembershipRole::Member);
        $ring = $this->heldRing($holder, $key);

        (new PassKeyAction)($manager, $ring, $receiver->getKey());

        Notification::assertSentTo($receiver, KeyPassedToNotification::class);
        Notification::assertNotSentTo($holder, KeyPassedFromNotification::class);
        Notification::assertCount(1);
    }

    public function test_passing_to_the_same_holder_or_cupboard_is_a_noop_without_mail(): void
    {
        Notification::fake();

        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $manager = User::factory()->teamManager($team)->create();
        $held = $this->heldRing($holder, $key, 'Held ring');
        $cupboard = $this->heldRing(null, $key, 'Cupboard ring');

        (new PassKeyAction)($holder, $held, $holder->getKey());
        (new PassKeyAction)($manager, $cupboard, null);

        $this->assertSame($holder->getKey(), $held->fresh()->holder_id);
        $this->assertNull($cupboard->fresh()->holder_id);
        Notification::assertNothingSent();
    }

    public function test_the_pass_picker_shows_names_without_emails_and_separates_the_cupboard(): void
    {
        [$team, $key] = $this->climbingTeam();
        $manager = User::factory()->teamManager($team)->create();
        $holder = User::factory()->teamMember($team)->create(['name' => 'Pat Holder']);
        $receiver = User::factory()->teamMember($team)->create([
            'name' => 'Alex Receiver',
            'email' => 'alex-receiver@example.com',
        ]);
        $ring = $this->heldRing($holder, $key);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);

        Livewire::test(ManageListKeys::class)
            ->mountTableAction('pass', $ring)
            ->assertSchemaComponentExists('holder_id', checkComponentUsing: function (Select $select) use ($receiver, $holder): bool {
                $options = $select->getOptions();
                $members = $options[__('Members')] ?? [];
                $cupboard = $options[__('Cupboard')] ?? [];

                return ($cupboard[Key::CUPBOARD] ?? null) === __('In the cupboard')
                    && ($members[(string) $receiver->getKey()] ?? null) === 'Alex Receiver'
                    && ! in_array('alex-receiver@example.com', $members, true)
                    && ! isset($members[(string) $holder->getKey()]);
            });
    }

    public function test_pass_mail_replies_to_the_key_address(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $receiver = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);
        $address = 'key+'.$ring->getKey().'@'.InboundMailAddress::domain();

        $to = (new KeyPassedToNotification($ring, $holder))->toMail($receiver);
        $from = (new KeyPassedFromNotification($ring, $receiver))->toMail($holder);

        $this->assertSame($address, $to->replyTo[0][0]);
        $this->assertSame($address, $from->replyTo[0][0]);
        $this->assertTrue($ring->is(InboundMailAddress::findResource($address)));
    }

    public function test_pass_mail_escapes_dangerous_names(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create([
            'name' => "Pat <script>alert('from')</script>",
        ]);
        $receiver = User::factory()->teamMember($team)->create([
            'name' => "Alex <script>alert('to')</script>",
        ]);
        $ring = $this->heldRing($holder, $key, "Store <script>alert('ring')</script>");

        $html = (string) (new KeyPassedToNotification($ring, $holder))->toMail($receiver)->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('from')</script>", $html);
        $this->assertStringNotContainsString("<script>alert('to')</script>", $html);
        $this->assertStringNotContainsString("<script>alert('ring')</script>", $html);
    }

    public function test_a_non_holder_member_is_refused_the_pass_pages(): void
    {
        [$team, $key] = $this->climbingTeam();
        $holder = User::factory()->teamMember($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $ring = $this->heldRing($holder, $key);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $this->get(MemberKeyResource::getUrl('view', ['record' => $ring]))
            ->assertNotFound();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($member);

        $this->get(ManageKeyResource::getUrl('index', tenant: $team))
            ->assertForbidden();
    }

    /**
     * @return array{0: Team, 1: Key}
     */
    private function climbingTeam(): array
    {
        $team = Team::factory()->create();
        $activity = Activity::factory()->create(['name' => 'Scout climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);

        $key = Lock::factory()->create(['name' => 'Climbing store']);
        $key->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);

        return [$team, $key];
    }

    private function heldRing(?User $holder, Lock $key, string $name = 'Shared climbing'): Key
    {
        $ring = $holder instanceof User
            ? Key::factory()->heldBy($holder)->create(['name' => $name])
            : Key::factory()->create(['name' => $name]);

        $ring->locks()->attach($key);

        return $ring;
    }
}
