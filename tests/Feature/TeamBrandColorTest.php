<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BrandColor;
use App\Filament\Admin\Resources\Teams\Pages\CreateTeam;
use App\Filament\Admin\Resources\Teams\Pages\EditTeam;
use App\Filament\Admin\Resources\Teams\Pages\ListTeams;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings;
use App\Filament\Member\Resources\NewsPosts\Pages\ListNewsPosts as MemberListNewsPosts;
use App\Filament\Member\Widgets\LatestNewsWidget;
use App\Filament\Member\Widgets\ThisWeekWidget;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Livewire\InviteAttendees;
use App\Models\Booking;
use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use App\Support\Avatars;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TeamBrandColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_null_colour_resolves_from_the_team_name_and_follows_renames(): void
    {
        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => null,
        ]);

        $this->assertNull($team->color);
        $this->assertSame(BrandColor::fromSeed('Archery'), $team->brandColor());

        $team->update(['name' => 'Climbing']);

        $this->assertSame(BrandColor::fromSeed('Climbing'), $team->fresh()->brandColor());
    }

    public function test_an_explicit_colour_stays_fixed_when_the_name_changes(): void
    {
        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Rose,
        ]);

        $team->update(['name' => 'Climbing']);

        $this->assertSame(BrandColor::Rose, $team->fresh()->color);
        $this->assertSame(BrandColor::Rose, $team->fresh()->brandColor());
    }

    public function test_creating_a_team_without_a_colour_persists_null(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'name' => 'Archery',
                'manager_id' => $manager->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::query()->where('name', 'Archery')->first();

        $this->assertNotNull($team);
        $this->assertNull($team->color);
        $this->assertSame(BrandColor::fromSeed('Archery'), $team->brandColor());
        $this->assertStringContainsString('ui-avatars.com/api/', (string) $team->getFilamentAvatarUrl());
        $this->assertStringContainsString('background='.$team->brandColor()->hex(), (string) $team->getFilamentAvatarUrl());
    }

    public function test_editing_a_team_can_lock_and_clear_a_colour(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create(['name' => 'Archery', 'color' => null]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditTeam::class, ['record' => $team->slug])
            ->assertFormFieldExists('color')
            ->assertDontSee('[object Object]')
            ->assertSee('Blue')
            ->fillForm([
                'color' => BrandColor::Teal->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(BrandColor::Teal, $team->fresh()->color);

        Livewire::test(EditTeam::class, ['record' => $team->fresh()->slug])
            ->fillForm([
                'color' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($team->fresh()->color);
    }

    public function test_the_admin_teams_list_shows_team_avatars(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create(['name' => 'Archery']);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListTeams::class)
            ->assertCanSeeTableRecords([$team])
            ->assertSee(str_replace('&', '&amp;', (string) $team->getFilamentAvatarUrl()), false);
    }

    public function test_the_admin_users_list_shows_member_avatars(): void
    {
        $admin = User::factory()->systemAdmin()->create([
            'email' => 'admin@example.com',
            'name' => 'Site Admin',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        $url = Avatars::userUrl($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertSee(str_replace('&', '&amp;', $url), false);

        $this->assertStringContainsString('gravatar.com/avatar/', $url);
        $this->assertStringContainsString(rawurlencode('https://ui-avatars.com/api/'), $url);
        $this->assertStringContainsString(BrandColor::fromSeed('Site Admin')->hex(), urldecode($url));
    }

    public function test_the_teams_members_list_shows_member_avatars(): void
    {
        $manager = User::factory()->teamManager()->create([
            'email' => 'manager@example.com',
            'name' => 'Team Manager',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$manager])
            ->assertSee(str_replace('&', '&amp;', Avatars::userUrl($manager)), false);
    }

    public function test_the_member_news_team_badge_uses_the_team_brand_colour(): void
    {
        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Violet,
        ]);
        $member = User::factory()->teamMember($team)->create();
        $post = NewsPost::factory()->forTeam($team)->published()->create(['title' => 'Wall night']);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $page = Livewire::test(MemberListNewsPosts::class)
            ->assertSuccessful()
            ->assertSee('Wall night')
            ->assertSee('Archery')
            ->instance();

        $this->assertSame(
            BrandColor::Violet->getColor(),
            $page->teamBadgeAction()->record($post)->getColor(),
        );
    }

    public function test_the_dashboard_latest_news_badge_uses_the_team_brand_colour(): void
    {
        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Teal,
        ]);
        $member = User::factory()->teamMember($team)->create();
        NewsPost::factory()->forTeam($team)->published()->create([
            'title' => 'Camp is on',
            'published_at' => now()->subDay(),
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(LatestNewsWidget::class)
            ->assertSee('Latest news')
            ->assertSee('Camp is on')
            ->assertSee('Archery')
            ->assertSeeHtml('--color-500: '.BrandColor::Teal->getColor()[500]);
    }

    public function test_the_dashboard_booking_row_colours_the_team_badge(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');

        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Rose,
        ]);
        $member = User::factory()->teamMember($team)->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'start_at' => '2026-11-05 18:00:00',
            'end_at' => '2026-11-05 20:00:00',
        ]);
        $booking->attendees()->attach($member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(ThisWeekWidget::class)
            ->assertSee('Archery')
            ->assertSeeHtml('--color-600:'.BrandColor::Rose->getColor()[600])
            ->assertDontSee((string) $team->getFilamentAvatarUrl(), false);
    }

    public function test_the_member_bookings_list_colours_the_team_badge(): void
    {
        $team = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Indigo,
        ]);
        $member = User::factory()->teamMember($team)->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create();
        $booking->attendees()->attach($member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(ListBookings::class)
            ->assertCanSeeTableRecords([$booking])
            ->assertSee('Archery')
            ->assertSeeHtml('--color-500: '.BrandColor::Indigo->getColor()[500]);
    }

    public function test_the_admin_users_list_colours_each_team_badge(): void
    {
        $climbing = Team::factory()->create([
            'name' => 'Climbing',
            'color' => BrandColor::Teal,
        ]);
        $archery = Team::factory()->create([
            'name' => 'Archery',
            'color' => BrandColor::Violet,
        ]);
        $admin = User::factory()->systemAdmin()->teamManager($climbing)->teamMember($archery)->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin])
            ->assertSee('Climbing')
            ->assertSee('Archery')
            ->assertSeeHtml('--color-500: '.BrandColor::Teal->getColor()[500])
            ->assertSeeHtml('--color-500: '.BrandColor::Violet->getColor()[500]);
    }

    public function test_the_memberships_relation_shows_a_team_avatar(): void
    {
        $team = Team::factory()->create(['name' => 'Archery', 'color' => BrandColor::Sky]);
        $admin = User::factory()->systemAdmin()->teamManager($team)->create();
        $membership = $admin->memberships()->where('team_id', $team->getKey())->firstOrFail();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $admin,
            'pageClass' => ViewUser::class,
        ])
            ->assertCanSeeTableRecords([$membership])
            ->assertSee('Archery')
            ->assertSee(str_replace('&', '&amp;', (string) $team->getFilamentAvatarUrl()), false);
    }

    public function test_the_guest_list_and_invite_picker_show_person_avatars(): void
    {
        $manager = User::factory()->teamManager()->create(['name' => 'Lead Person', 'email' => 'lead@example.com']);
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->teamMember($team)->create([
            'name' => 'Accepted Attendee',
            'email' => 'accepted@example.com',
        ]);
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($manager->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $booking->attendees()->attach($member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->assertSee('Accepted Attendee')
            ->assertSee(str_replace('&', '&amp;', Avatars::userUrl($member, 64)), false);

        Livewire::test(InviteAttendees::class, ['booking' => $booking])
            ->assertSee('Accepted Attendee')
            ->assertSee(str_replace('&', '&amp;', Avatars::userUrl($member, 64)), false);
    }
}
