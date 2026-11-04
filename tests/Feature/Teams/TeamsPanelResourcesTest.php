<?php

namespace Tests\Feature\Teams;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Teams\Pages\Leaders;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Filament\Teams\Resources\Documents\DocumentResource;
use App\Filament\Teams\Resources\Documents\Pages\ListDocuments;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Widgets\LatestNewsWidget;
use App\Filament\Teams\Widgets\ThisWeekWidget;
use App\Livewire\TeamLeaderContactDetails;
use App\Models\Booking;
use App\Models\Document;
use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamsPanelResourcesTest extends TestCase
{
    use RefreshDatabase;

    private Team $climbing;

    private Team $archery;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->climbing = Team::factory()->create(['name' => 'Climbing']);
        $this->archery = Team::factory()->create(['name' => 'Archery']);
        $this->member = User::factory()->create();
        $this->climbing->grant($this->member, MembershipRole::Member);
        $this->archery->grant($this->member, MembershipRole::Member);

        $this->actingAs($this->member);
        Filament::setCurrentPanel(Filament::getPanel('teams'));
        Filament::setTenant($this->climbing);
    }

    public function test_bookings_are_scoped_to_the_active_team(): void
    {
        $climbing = Booking::factory()->forTeam($this->climbing)->confirmed()->create(['activity_label' => 'Climbing group']);
        $archery = Booking::factory()->forTeam($this->archery)->confirmed()->create(['activity_label' => 'Archery group']);

        $this->get(BookingResource::getUrl('index', tenant: $this->climbing))
            ->assertOk()
            ->assertSee('Climbing group')
            ->assertDontSee('Archery group');

        $this->get(BookingResource::getUrl('view', ['record' => $climbing], tenant: $this->climbing))->assertOk();
        $this->get(BookingResource::getUrl('view', ['record' => $archery], tenant: $this->climbing))->assertNotFound();
    }

    public function test_documents_are_scoped_to_the_active_team(): void
    {
        $climbing = Document::factory()->forTeam($this->climbing)->create(['title' => 'Climbing rules']);
        Document::factory()->forTeam($this->archery)->create(['title' => 'Archery rules']);

        $this->get(DocumentResource::getUrl('index', tenant: $this->climbing))
            ->assertOk()
            ->assertSee('Climbing rules')
            ->assertDontSee('Archery rules');

        $this->get(DocumentResource::getUrl('view', ['record' => $climbing], tenant: $this->climbing))->assertOk();
    }

    public function test_news_is_scoped_to_the_active_team_and_hides_drafts(): void
    {
        $climbing = NewsPost::factory()->forTeam($this->climbing)->published()->create(['title' => 'Climbing news', 'published_at' => now()->subDay()]);
        NewsPost::factory()->forTeam($this->archery)->published()->create(['title' => 'Archery news', 'published_at' => now()->subDay()]);
        NewsPost::factory()->forTeam($this->climbing)->create(['title' => 'Climbing draft', 'published_at' => now()->addDay()]);

        $this->get(NewsPostResource::getUrl('index', tenant: $this->climbing))
            ->assertOk()
            ->assertSee('Climbing news')
            ->assertDontSee('Archery news')
            ->assertDontSee('Climbing draft');

        $this->get(NewsPostResource::getUrl('view', ['record' => $climbing], tenant: $this->climbing))->assertOk();
    }

    public function test_people_cannot_open_a_team_they_do_not_belong_to(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(NewsPostResource::getUrl('index', tenant: $this->climbing))
            ->assertNotFound();
    }

    public function test_dashboard_widgets_only_show_the_active_team(): void
    {
        $start = now()->addDays(2)->setTime(18, 0);
        $climbing = Booking::factory()->forTeam($this->climbing)->confirmed()->create(['start_at' => $start, 'end_at' => $start->copy()->addHours(2), 'activity_label' => 'Climbing group']);
        $archery = Booking::factory()->forTeam($this->archery)->confirmed()->create(['start_at' => $start, 'end_at' => $start->copy()->addHours(2), 'activity_label' => 'Archery group']);
        $climbing->attendees()->attach($this->member->id, ['status' => 'accepted']);
        $archery->attendees()->attach($this->member->id, ['status' => 'accepted']);
        NewsPost::factory()->forTeam($this->climbing)->create(['title' => 'Climbing news', 'published_at' => now()->subDay()]);
        NewsPost::factory()->forTeam($this->archery)->create(['title' => 'Archery news', 'published_at' => now()->subDay()]);

        $this->get(Filament::getPanel('teams')->getUrl($this->climbing))->assertOk();

        Livewire::test(ThisWeekWidget::class)
            ->assertSee('Climbing group')
            ->assertDontSee('Archery group');

        Livewire::test(LatestNewsWidget::class)
            ->assertSee('Climbing news')
            ->assertDontSee('Archery news');
    }

    public function test_documents_are_sorted_by_title_and_can_be_sorted_by_date(): void
    {
        $zebra = Document::factory()->forTeam($this->climbing)->create(['title' => 'Zebra', 'updated_at' => now()->subDays(2)]);
        $alpha = Document::factory()->forTeam($this->climbing)->create(['title' => 'Alpha', 'updated_at' => now()->subDay()]);
        $middle = Document::factory()->forTeam($this->climbing)->create(['title' => 'Middle', 'updated_at' => now()]);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$alpha, $middle, $zebra], inOrder: true)
            ->sortTable('title', 'desc')
            ->assertCanSeeTableRecords([$zebra, $middle, $alpha], inOrder: true)
            ->sortTable('updated_at', 'desc')
            ->assertCanSeeTableRecords([$middle, $alpha, $zebra], inOrder: true);
    }

    public function test_documents_show_notes_and_can_be_downloaded_without_naming_the_uploader(): void
    {
        $manager = User::factory()->teamManager($this->climbing)->create(['name' => 'Zelda Uploader']);
        $document = Document::factory()->forTeam($this->climbing)->create([
            'title' => 'Wall procedure',
            'notes' => 'Keep this with the kit.',
            'uploader_id' => $manager->getKey(),
        ]);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$document])
            ->assertSeeText('Keep this with the kit.')
            ->assertDontSeeText('Zelda Uploader')
            ->callTableAction('download', $document)
            ->assertSuccessful();

        $this->get(DocumentResource::getUrl('view', ['record' => $document], tenant: $this->climbing))
            ->assertOk()
            ->assertDontSee('Zelda Uploader');
    }

    public function test_a_suspended_member_cannot_open_team_documents(): void
    {
        $this->member->update(['suspended' => true]);
        Document::factory()->forTeam($this->climbing)->create();

        $this->get(DocumentResource::getUrl('index', tenant: $this->climbing))->assertForbidden();
    }

    public function test_the_booking_list_is_sorted_by_start_time_and_has_invites_and_rota_tabs(): void
    {
        $later = Booking::factory()->forTeam($this->climbing)->confirmed()->create(['start_at' => now()->addDays(5), 'end_at' => now()->addDays(5)->addHours(2)]);
        $sooner = Booking::factory()->forTeam($this->climbing)->confirmed()->create(['start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addHours(2)]);
        $sooner->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::NeedsAction]);
        $later->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted]);

        Livewire::test(ListBookings::class)
            ->assertCanSeeTableRecords([$sooner, $later], inOrder: true)
            ->set('activeTab', 'invites')
            ->assertCanSeeTableRecords([$sooner])
            ->assertCanNotSeeTableRecords([$later])
            ->set('activeTab', 'rota')
            ->assertCanSeeTableRecords([$later])
            ->assertCanNotSeeTableRecords([$sooner]);
    }

    public function test_a_manager_sees_their_managed_teams_drafts_in_the_teams_workspace(): void
    {
        $this->climbing->grant($this->member, MembershipRole::Manager);
        $climbingDraft = Booking::factory()->forTeam($this->climbing)->create(['activity_label' => 'Climbing draft']);
        $archeryDraft = Booking::factory()->forTeam($this->archery)->create(['activity_label' => 'Archery draft']);
        $archeryConfirmed = Booking::factory()->forTeam($this->archery)->confirmed()->create(['activity_label' => 'Archery confirmed']);

        $this->get(BookingResource::getUrl('index', tenant: $this->climbing))
            ->assertOk()
            ->assertSee('Climbing draft');
        $this->get(BookingResource::getUrl('view', ['record' => $climbingDraft], tenant: $this->climbing))->assertOk();

        $this->get(BookingResource::getUrl('index', tenant: $this->archery))
            ->assertOk()
            ->assertSee('Archery confirmed')
            ->assertDontSee('Archery draft');
        $this->get(BookingResource::getUrl('view', ['record' => $archeryConfirmed], tenant: $this->archery))->assertOk();
        $this->get(BookingResource::getUrl('view', ['record' => $archeryDraft], tenant: $this->archery))->assertNotFound();
    }

    public function test_leaders_lists_the_active_teams_managers_alphabetically_without_contact_details(): void
    {
        $zara = User::factory()->create(['name' => 'Zara Leader', 'email' => 'zara@example.test', 'phone' => '07700 900111']);
        $abe = User::factory()->create(['name' => 'Abe Leader']);
        $archeryLeader = User::factory()->create(['name' => 'Archery Leader']);
        $this->climbing->grant($zara, MembershipRole::Manager);
        $this->climbing->grant($abe, MembershipRole::Manager);
        $this->archery->grant($archeryLeader, MembershipRole::Manager);

        $this->get(Leaders::getUrl(tenant: $this->climbing))
            ->assertOk()
            ->assertSeeInOrder(['Abe Leader', 'Zara Leader'])
            ->assertSee('Manager')
            ->assertDontSee('Archery Leader')
            ->assertDontSee('zara@example.test')
            ->assertDontSee('07700 900111');
    }

    public function test_leader_contact_details_need_the_acknowledgement_first(): void
    {
        $leader = User::factory()->create(['email' => 'leader@example.test', 'phone' => '07700 900222']);
        $this->climbing->grant($leader, MembershipRole::Manager);

        Livewire::test(TeamLeaderContactDetails::class, ['team' => $this->climbing, 'person' => $leader])
            ->assertSee('legitimate reason')
            ->assertDontSee('leader@example.test')
            ->call('acknowledge')
            ->assertSee('leader@example.test')
            ->assertSee('07700 900222');
    }

    public function test_leader_contact_details_are_only_for_managers_of_a_team_the_viewer_belongs_to(): void
    {
        $archeryLeader = User::factory()->create();
        $this->archery->grant($archeryLeader, MembershipRole::Manager);
        $stranger = User::factory()->create();
        $this->climbing->grant($stranger, MembershipRole::Member);

        Livewire::test(TeamLeaderContactDetails::class, ['team' => $this->climbing, 'person' => $archeryLeader])
            ->assertForbidden();
        Livewire::test(TeamLeaderContactDetails::class, ['team' => $this->climbing, 'person' => $stranger])
            ->assertForbidden();

        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->get(Leaders::getUrl(tenant: $this->climbing))->assertNotFound();
    }

    public function test_the_leaders_contact_action_opens_the_acknowledgement_modal(): void
    {
        $leader = User::factory()->create(['name' => 'Abe Leader', 'email' => 'abe@example.test']);
        $this->climbing->grant($leader, MembershipRole::Manager);

        Livewire::test(Leaders::class)
            ->mountAction('contact', ['leader' => $leader->getKey()])
            ->assertActionMounted('contact')
            ->assertSee('Abe Leader')
            ->assertDontSee('abe@example.test');
    }
}
