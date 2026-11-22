<?php

namespace Tests\Feature;

use App\Enums\TaskAttendeeStatus;
use App\Enums\TeamFeature;
use App\Filament\Admin\Resources\Activities\ActivityResource as AdminActivityResource;
use App\Filament\Admin\Resources\Audiences\AudienceResource as AdminAudienceResource;
use App\Filament\Admin\Resources\InboundMails\InboundMailResource as AdminInboundMailResource;
use App\Filament\Admin\Resources\Keys\KeyResource as AdminKeyResource;
use App\Filament\Admin\Resources\Locks\LockResource as AdminLockResource;
use App\Filament\Admin\Resources\Teams\TeamResource as AdminTeamResource;
use App\Filament\Admin\Resources\Users\UserResource as AdminUserResource;
use App\Filament\Admin\Resources\Venues\VenueResource as AdminVenueResource;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Member\Resources\Keys\KeyResource as MemberKeyResource;
use App\Filament\Member\Resources\NewsPosts\NewsPostResource as MemberNewsPostResource;
use App\Filament\Member\Resources\Qualifications\QualificationResource as MemberQualificationResource;
use App\Filament\Member\Resources\Tasks\TaskResource as MemberTaskResource;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Filament\Teams\Resources\Documents\DocumentResource as TeamsDocumentResource;
use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource as TeamsEquipmentSetResource;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamsKeyResource;
use App\Filament\Teams\Resources\Members\MemberResource as TeamsMemberResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource as TeamsNewsPostResource;
use App\Filament\Teams\Resources\Tasks\TaskResource as TeamsTaskResource;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\Document;
use App\Models\EquipmentSet;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Lock;
use App\Models\NewsPost;
use App\Models\Qualification;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\Resources\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_manager_global_search_finds_each_searchable_resource(): void
    {
        $marker = 'ZxqSearchMgr';
        $manager = User::factory()->teamManager()->create(['name' => "{$marker} Manager"]);
        $team = $manager->managedTeams()->firstOrFail();
        $this->enableTeamSearchFeatures($team);

        $member = User::factory()->teamMember($team)->create(['name' => "{$marker} Member"]);
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create();
        $venue = Venue::factory()->create();
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);
        $lock = Lock::factory()->requiredFor($activity, $venue)->create();

        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => "{$marker} Booking",
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);
        Task::factory()->forTeam($team)->create(['summary' => "{$marker} Task"]);
        Document::factory()->forTeam($team)->create(['title' => "{$marker} Document"]);
        NewsPost::factory()->forTeam($team)->published()->create(['title' => "{$marker} News"]);
        EquipmentSet::factory()->forTeam($team)->create(['name' => "{$marker} Kit"]);
        Key::factory()->heldBy($member)->unlocks($lock)->create(['name' => "{$marker} Key"]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $categories = $this->searchCategories($marker);

        $this->assertCategoryContains($categories, TeamsBookingResource::class, "{$marker} Booking · Scouts");
        $this->assertCategoryContains($categories, TeamsMemberResource::class, "{$marker} Member");
        $this->assertCategoryContains($categories, TeamsTaskResource::class, "{$marker} Task");
        $this->assertCategoryContains($categories, TeamsDocumentResource::class, "{$marker} Document");
        $this->assertCategoryContains($categories, TeamsNewsPostResource::class, "{$marker} News");
        $this->assertCategoryContains($categories, TeamsEquipmentSetResource::class, "{$marker} Kit");
        $this->assertCategoryContains($categories, TeamsKeyResource::class, "{$marker} Key");
    }

    public function test_teams_member_global_search_omits_manager_only_resources(): void
    {
        $marker = 'ZxqSearchMem';
        $team = Team::factory()->create();
        $this->enableTeamSearchFeatures($team);
        $manager = User::factory()->teamManager($team)->create(['name' => "{$marker} Manager"]);
        $member = User::factory()->teamMember($team)->create(['name' => "{$marker} Member"]);
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create();
        $venue = Venue::factory()->create();
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);
        $lock = Lock::factory()->requiredFor($activity, $venue)->create();

        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => "{$marker} Booking",
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);
        Task::factory()->forTeam($team)->create(['summary' => "{$marker} Task"]);
        Document::factory()->forTeam($team)->create(['title' => "{$marker} Document"]);
        NewsPost::factory()->forTeam($team)->published()->create(['title' => "{$marker} News"]);
        EquipmentSet::factory()->forTeam($team)->create(['name' => "{$marker} Kit"]);
        Key::factory()->heldBy($manager)->unlocks($lock)->create(['name' => "{$marker} Key"]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        $categories = $this->searchCategories($marker);

        $this->assertCategoryContains($categories, TeamsBookingResource::class, "{$marker} Booking · Scouts");
        $this->assertCategoryContains($categories, TeamsTaskResource::class, "{$marker} Task");
        $this->assertCategoryContains($categories, TeamsDocumentResource::class, "{$marker} Document");
        $this->assertCategoryContains($categories, TeamsNewsPostResource::class, "{$marker} News");
        $this->assertCategoryMissing($categories, TeamsMemberResource::class);
        $this->assertCategoryMissing($categories, TeamsKeyResource::class);
        $this->assertCategoryMissing($categories, TeamsEquipmentSetResource::class);
    }

    public function test_teams_global_search_stays_within_the_current_tenant(): void
    {
        $marker = 'ZxqSearchTenant';
        $home = Team::factory()->create();
        $away = Team::factory()->create();
        $manager = User::factory()->teamManager($home)->create();
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create();

        Booking::factory()->forTeam($home)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => "{$marker} Home",
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);
        Booking::factory()->forTeam($away)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => "{$marker} Away",
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Filament::setTenant($home);

        $categories = $this->searchCategories($marker);

        $this->assertCategoryContains($categories, TeamsBookingResource::class, "{$marker} Home · Scouts");
        $this->assertSame(
            ["{$marker} Home · Scouts"],
            $this->categoryTitles($categories, TeamsBookingResource::class),
        );
    }

    public function test_member_panel_global_search_finds_each_personal_resource(): void
    {
        $marker = 'ZxqSearchPersonal';
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();
        $other = User::factory()->teamMember($team)->create();

        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create();
        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => "{$marker} Booking",
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);

        $mine = Task::factory()->forTeam($team)->create(['summary' => "{$marker} Task"]);
        TaskAttendance::query()->create([
            'task_id' => $mine->getKey(),
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::Accepted,
        ]);
        Task::factory()->forTeam($team)->create(['summary' => "{$marker} OtherTask"]);

        Key::factory()->heldBy($member)->create(['name' => "{$marker} Key"]);
        Key::factory()->heldBy($other)->create(['name' => "{$marker} OtherKey"]);

        Qualification::factory()->for($member)->scoutPermit([
            'restrictions' => "{$marker} only",
        ])->create();
        Qualification::factory()->for($other)->archery()->create();

        NewsPost::factory()->forTeam($team)->published()->create(['title' => "{$marker} News"]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $categories = $this->searchCategories($marker);

        $this->assertCategoryContains($categories, MemberBookingResource::class, "{$marker} Booking · Scouts");
        $this->assertCategoryContains($categories, MemberTaskResource::class, "{$marker} Task");
        $this->assertSame(["{$marker} Task"], $this->categoryTitles($categories, MemberTaskResource::class));
        $this->assertCategoryContains($categories, MemberKeyResource::class, "{$marker} Key");
        $this->assertSame(["{$marker} Key"], $this->categoryTitles($categories, MemberKeyResource::class));
        $this->assertCategoryContains(
            $categories,
            MemberQualificationResource::class,
            'Climbing and Abseiling — Artificial Top Rope — Leadership',
        );
        $this->assertCount(1, $this->categoryTitles($categories, MemberQualificationResource::class));
        $this->assertCategoryContains($categories, MemberNewsPostResource::class, "{$marker} News");
    }

    public function test_admin_global_search_finds_each_catalog_resource(): void
    {
        $marker = 'ZxqSearchAdmin';
        $admin = User::factory()->systemAdmin()->create(['name' => "{$marker} Admin"]);

        Activity::factory()->create(['name' => "{$marker} Activity"]);
        Audience::factory()->create(['name' => "{$marker} Audience"]);
        Venue::factory()->create(['name' => "{$marker} Venue"]);
        Lock::factory()->create(['name' => "{$marker} Lock"]);
        Key::factory()->create(['name' => "{$marker} Key"]);
        Team::factory()->create(['name' => "{$marker} Team"]);
        User::factory()->create(['name' => "{$marker} User"]);
        InboundMail::factory()->create(['subject' => "{$marker} Mail"]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        $categories = $this->searchCategories($marker);

        $this->assertCategoryContains($categories, AdminActivityResource::class, "{$marker} Activity");
        $this->assertCategoryContains($categories, AdminAudienceResource::class, "{$marker} Audience");
        $this->assertCategoryContains($categories, AdminVenueResource::class, "{$marker} Venue");
        $this->assertCategoryContains($categories, AdminLockResource::class, "{$marker} Lock");
        $this->assertCategoryContains($categories, AdminKeyResource::class, "{$marker} Key");
        $this->assertCategoryContains($categories, AdminTeamResource::class, "{$marker} Team");
        $this->assertCategoryContains($categories, AdminUserResource::class, "{$marker} Admin");
        $this->assertCategoryContains($categories, AdminUserResource::class, "{$marker} User");
        $this->assertCategoryContains($categories, AdminInboundMailResource::class, "{$marker} Mail");
    }

    public function test_team_manager_cannot_search_admin_catalog_resources(): void
    {
        $marker = 'ZxqSearchDenied';
        $manager = User::factory()->teamManager()->create();
        Activity::factory()->create(['name' => "{$marker} Activity"]);
        Key::factory()->create(['name' => "{$marker} Key"]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($manager);

        $this->assertFalse($manager->canAccessPanel(Filament::getPanel('admin')));
        $this->assertSame([], $this->categoryTitles($this->searchCategories($marker), AdminActivityResource::class));
        $this->assertSame([], $this->categoryTitles($this->searchCategories($marker), AdminKeyResource::class));
    }

    /**
     * @return Collection<string, Collection<int, GlobalSearchResult>|array<int, GlobalSearchResult>>
     */
    private function searchCategories(string $query): Collection
    {
        $results = Filament::getGlobalSearchProvider()->getResults($query);

        $this->assertInstanceOf(GlobalSearchResults::class, $results);

        return $results->getCategories();
    }

    /**
     * @param  Collection<string, mixed>  $categories
     * @param  class-string<resource>  $resource
     * @return list<string>
     */
    private function categoryTitles(Collection $categories, string $resource): array
    {
        $items = $categories->get($resource::getPluralModelLabel()) ?? [];

        return collect($items)
            ->map(fn (GlobalSearchResult $result): string => (string) $result->title)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, mixed>  $categories
     * @param  class-string<resource>  $resource
     */
    private function assertCategoryContains(Collection $categories, string $resource, string $title): void
    {
        $this->assertContains($title, $this->categoryTitles($categories, $resource));
    }

    /**
     * @param  Collection<string, mixed>  $categories
     * @param  class-string<resource>  $resource
     */
    private function assertCategoryMissing(Collection $categories, string $resource): void
    {
        $this->assertFalse(
            $categories->has($resource::getPluralModelLabel()),
            sprintf('Did not expect global search category [%s].', $resource::getPluralModelLabel()),
        );
    }

    private function enableTeamSearchFeatures(Team $team): void
    {
        $team->forceFill([
            'features' => [
                TeamFeature::Tasks->value => true,
                TeamFeature::Debrief->value => [
                    'enabled' => true,
                    'activated_at' => now()->subYear()->toIso8601String(),
                ],
            ],
        ])->save();
    }
}
