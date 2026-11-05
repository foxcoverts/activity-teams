<?php

namespace Tests\Feature;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\NewsPosts\Pages\ListNewsPosts;
use App\Filament\Manage\Resources\NewsPosts\Pages\ViewNewsPost;
use App\Filament\Member\Pages\Feeds;
use App\Filament\Member\Resources\NewsPosts\NewsPostResource as MemberNewsPostResource;
use App\Filament\Member\Resources\NewsPosts\Pages\ListNewsPosts as MemberListNewsPosts;
use App\Jobs\NotifyDueNewsPosts;
use App\Mail\NewsPostMail;
use App\Models\NewsPost;
use App\Models\Team;
use App\Models\User;
use App\Support\CombinedDigest;
use App\Support\MailPreference;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NewsPostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('manage');
    }

    public function test_manage_news_posts_table_can_be_accessed(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $posts = NewsPost::factory()->forTeam($team)->count(4)->create();
        $trashedPosts = NewsPost::factory()->forTeam($team)->trashed()->count(3)->create();

        Livewire::test(ListNewsPosts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($posts)
            ->assertCanNotSeeTableRecords($trashedPosts)
            ->assertCountTableRecords(4)
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('author.name')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('published_at')
            ->assertTableColumnHidden('deleted_at');
    }

    public static function provideNewsPostStatuses(): array
    {
        return [
            ['draft', 'Draft'],
            ['published', 'Published'],
            ['scheduled', 'Scheduled'],
        ];
    }

    #[DataProvider('provideNewsPostStatuses')]
    public function test_manage_news_posts_table_shows_fields(string $status, string $label): void
    {
        $manager = User::factory()->teamManager()->create([
            'name' => 'John Smith',
        ]);
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $post = NewsPost::factory()->forTeam($team)->{$status}()->create([
            'title' => $label.' News Post',
            'author_id' => $manager->getKey(),
        ]);

        $formattedPublishedAt = match ($status) {
            'draft' => '',
            'published', 'scheduled' => $post->published_at->since(),
        };

        Livewire::test(ListNewsPosts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$post])
            ->assertTableColumnFormattedStateSet('title', $label.' News Post', record: $post)
            ->assertTableColumnFormattedStateSet('author.name', 'John Smith', record: $post)
            ->assertTableColumnFormattedStateSet('status', $label, record: $post)
            ->assertTableColumnFormattedStateSet('published_at', $formattedPublishedAt, record: $post);
    }

    public function test_manage_news_posts_table_can_be_sorted(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        // Sort: published_at

        NewsPost::factory()->forTeam($team)->published()->count(10)->create();

        $sortedPostsAsc = NewsPost::query()->forTeam($team)->orderBy('published_at', 'asc')->get();
        $sortedPostsDesc = NewsPost::query()->forTeam($team)->orderBy('published_at', 'desc')->get();

        Livewire::test(ListNewsPosts::class)
            ->assertSuccessful()
            ->sortTable('published_at', 'desc')
            ->assertCanSeeTableRecords($sortedPostsDesc, inOrder: true)
            ->sortTable('published_at', 'asc')
            ->assertCanSeeTableRecords($sortedPostsAsc, inOrder: true);

        // Filter & sort: trashed & deleted_at

        NewsPost::factory()->forTeam($team)->trashed()->count(10)->create();
        $trashedPostsAsc = NewsPost::query()->forTeam($team)->onlyTrashed()->orderBy('deleted_at', 'asc')->get();
        $trashedPostsDesc = NewsPost::query()->forTeam($team)->onlyTrashed()->orderBy('deleted_at', 'desc')->get();

        Livewire::test(ListNewsPosts::class)
            ->set('tableRecordsPerPage', 25)
            ->assertSuccessful()
            ->filterTable('trashed', '') // without deleted records
            ->assertTableColumnHidden('deleted_at')
            ->filterTable('trashed', '1') // with deleted records
            ->assertTableColumnVisible('deleted_at')
            ->assertCanRenderTableColumn('deleted_at')
            ->assertCanSeeTableRecords([...$sortedPostsAsc, ...$trashedPostsAsc])
            ->filterTable('trashed', '0') // only deleted records
            ->assertTableColumnVisible('deleted_at')
            ->sortTable('deleted_at', 'asc')
            ->assertCanSeeTableRecords($trashedPostsAsc, inOrder: true)
            ->sortTable('deleted_at', 'desc')
            ->assertCanSeeTableRecords($trashedPostsDesc, inOrder: true);
    }

    public function test_manage_news_posts_table_can_be_filtered_by_status(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $draftPosts = NewsPost::factory()->forTeam($team)->draft()->count(5)->create();
        $publishedPosts = NewsPost::factory()->forTeam($team)->published()->count(5)->create();
        $scheduledPosts = NewsPost::factory()->forTeam($team)->scheduled()->count(5)->create();

        Livewire::test(ListNewsPosts::class)
            ->assertSuccessful()
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords($draftPosts)
            ->assertCanNotSeeTableRecords($publishedPosts)
            ->assertCanNotSeeTableRecords($scheduledPosts)
            ->filterTable('status', 'published')
            ->assertCanNotSeeTableRecords($draftPosts)
            ->assertCanSeeTableRecords($publishedPosts)
            ->assertCanNotSeeTableRecords($scheduledPosts)
            ->filterTable('status', 'scheduled')
            ->assertCanNotSeeTableRecords($draftPosts)
            ->assertCanNotSeeTableRecords($publishedPosts)
            ->assertCanSeeTableRecords($scheduledPosts);
    }

    public function test_manage_news_posts_view_can_publish_draft_post(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $draftPost = NewsPost::factory()->forTeam($team)->draft()->create();
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $draftPost->slug])
            ->assertSuccessful()
            ->assertSee('By '.$manager->name)
            ->assertSee('Draft')
            ->assertActionVisible('publish')
            ->assertActionHidden('republish')
            ->callAction('publish')
            ->assertSee('Published');

        $this->assertEqualsWithDelta(now(), $draftPost->fresh()->published_at, 1);
    }

    public function test_manage_news_posts_view_can_schedule_draft_post(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $draftPost = NewsPost::factory()->forTeam($team)->draft()->create();
        $this->freezeTime();

        $scheduledAt = now()->addDays(2)->seconds(0);

        Livewire::test(ViewNewsPost::class, ['record' => $draftPost->slug])
            ->assertSuccessful()
            ->assertSchemaStateSet([
                'published_at' => null,
            ])
            ->assertSee('By '.$manager->name)
            ->assertSee('Draft')
            ->callAction('schedule', [
                'published_at' => $scheduledAt,
            ])
            ->assertSee('Scheduled');

        $this->assertEqualsWithDelta($scheduledAt, $draftPost->fresh()->published_at, 1);
    }

    public function test_manage_news_posts_view_can_unpublish_published_post(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $publishedPost = NewsPost::factory()->forTeam($team)->published()->create();
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $publishedPost->slug])
            ->assertSuccessful()
            ->assertSee('Published')
            ->callAction('unpublish')
            ->assertSee('Draft');

        $this->assertNull($publishedPost->fresh()->published_at);
    }

    public function test_manage_news_posts_view_can_unpublish_scheduled_post(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $scheduledPost = NewsPost::factory()->forTeam($team)->scheduled()->create();
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $scheduledPost->slug])
            ->assertSuccessful()
            ->assertSee('Scheduled')
            ->callAction('unpublish')
            ->assertSee('Draft');

        $this->assertNull($scheduledPost->fresh()->published_at);
    }

    public function test_manage_news_posts_view_renders_the_body_as_markdown(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $post = NewsPost::factory()->forTeam($team)->published()->create([
            'body' => "Meet at the **wall**.\n\n- Bring shoes",
        ]);

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->assertSuccessful()
            ->assertSeeHtml('<strong>wall</strong>')
            ->assertSeeHtml('<li>Bring shoes</li>');
    }

    public function test_publish_and_republish_are_never_both_visible(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager);

        $draft = NewsPost::factory()->forTeam($team)->draft()->create();

        Livewire::test(ViewNewsPost::class, ['record' => $draft->slug])
            ->assertActionVisible('publish')
            ->assertActionVisible('schedule')
            ->assertActionHidden('republish')
            ->assertActionHidden('unpublish');

        $notifiedDraft = NewsPost::factory()->forTeam($team)->draft()->notified()->create();

        Livewire::test(ViewNewsPost::class, ['record' => $notifiedDraft->slug])
            ->assertActionHidden('publish')
            ->assertActionVisible('republish')
            ->assertActionVisible('schedule')
            ->assertActionHidden('unpublish');

        $scheduled = NewsPost::factory()->forTeam($team)->scheduled()->create();

        Livewire::test(ViewNewsPost::class, ['record' => $scheduled->slug])
            ->assertActionHidden('publish')
            ->assertActionHidden('republish')
            ->assertActionHidden('schedule')
            ->assertActionVisible('unpublish');

        $live = NewsPost::factory()->forTeam($team)->published()->notified()->create();

        Livewire::test(ViewNewsPost::class, ['record' => $live->slug])
            ->assertActionHidden('publish')
            ->assertActionHidden('republish')
            ->assertActionHidden('schedule')
            ->assertActionVisible('unpublish');
    }

    public function test_unpublish_of_a_scheduled_post_returns_it_to_draft_without_notifying(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();

        $this->actingAs($manager);

        $scheduled = NewsPost::factory()->forTeam($team)->scheduled()->create();
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $scheduled->slug])
            ->callAction('unpublish');

        $scheduled->refresh();
        $this->assertNull($scheduled->published_at);
        $this->assertNull($scheduled->notified_at);
        $this->assertSame(0, $member->notifications()->count());
    }

    public function test_republish_without_send_update_does_not_write_a_second_inbox_row(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->draft()->create();

        $this->actingAs($manager);
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('publish');

        $this->assertSame(1, $member->notifications()->count());

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('unpublish')
            ->assertActionVisible('republish')
            ->assertActionHidden('publish')
            ->callAction('republish', ['send_update' => false]);

        $this->assertSame(1, $member->notifications()->count());
        $this->assertNotNull($post->fresh()->published_at);
        $this->assertNotNull($post->fresh()->notified_at);
    }

    public function test_republish_with_send_update_writes_a_new_inbox_row(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->draft()->create();

        $this->actingAs($manager);
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('publish');

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('unpublish')
            ->callAction('republish', ['send_update' => true]);

        $this->assertSame(2, $member->notifications()->count());
    }

    public function test_past_dated_save_stamps_notified_at_and_the_due_job_notifies_nobody(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->draft()->create([
            'title' => 'Retrospective post',
        ]);

        $this->actingAs($manager);
        $this->freezeTime();
        $publishedAt = now()->subDays(3)->seconds(0);

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('schedule', [
                'published_at' => $publishedAt,
                'confirm_past' => true,
            ]);

        $post->refresh();
        $this->assertEqualsWithDelta($publishedAt, $post->published_at, 1);
        $this->assertEqualsWithDelta($publishedAt, $post->notified_at, 1);

        (new NotifyDueNewsPosts)->handle();

        $this->assertSame(0, $member->notifications()->count());

        Filament::setCurrentPanel('member');
        $this->actingAs($member)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertSee('Retrospective post');
    }

    public function test_future_schedule_is_absent_from_the_feed_until_it_is_due(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->draft()->create([
            'title' => 'Later news',
        ]);

        $this->actingAs($manager);
        $this->freezeTime();
        $scheduledAt = now()->addDays(2)->seconds(0);

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('schedule', [
                'published_at' => $scheduledAt,
            ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertDontSee('Later news');

        $this->travelTo($scheduledAt);
        (new NotifyDueNewsPosts)->handle();

        $this->actingAs($member)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertSee('Later news');
        $this->assertSame(1, $member->notifications()->count());
        $this->assertNotNull($post->fresh()->notified_at);
    }

    public function test_members_see_an_article_feed_isolated_by_team(): void
    {
        $teamA = Team::factory()->create(['name' => 'Alpha', 'slug' => 'alpha']);
        $teamB = Team::factory()->create(['name' => 'Bravo', 'slug' => 'bravo']);
        $managerA = User::factory()->teamManager($teamA)->create(['name' => 'Ada Manager']);
        $memberA = User::factory()->teamMember($teamA)->create();
        $memberB = User::factory()->teamMember($teamB)->create();
        $post = NewsPost::factory()->forTeam($teamA)->published()->create([
            'title' => 'Alpha wall night',
            'body' => "## Office Keys\n\nBring **shoes** and a bottle.",
            'author_id' => $managerA->getKey(),
        ]);

        Filament::setCurrentPanel('member');

        $this->actingAs($memberA)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertSee('Alpha wall night')
            ->assertSee('Ada Manager')
            ->assertSee('Alpha')
            ->assertSee('Bring shoes and a bottle.')
            ->assertSee(__('Read more...'))
            ->assertSeeHtml('<article')
            ->assertSeeHtml('fi-section')
            ->assertDontSee('fi-ta', false)
            ->assertSeeInOrder(['News', 'List']);

        $this->actingAs($memberA)
            ->get($post->memberUrl())
            ->assertOk()
            ->assertSee('Alpha wall night')
            ->assertSeeHtml('<h2>Office Keys</h2>')
            ->assertSeeHtml('<strong>shoes</strong>')
            ->assertSeeHtml('fi-prose')
            ->assertSeeInOrder(['News', 'Alpha', 'Alpha wall night', 'View'])
            ->assertDontSee('/news?team=', false);

        $this->actingAs($memberB)
            ->get($post->memberUrl())
            ->assertNotFound();

        $this->actingAs($memberA);

        Livewire::test(MemberListNewsPosts::class)
            ->assertSuccessful()
            ->assertSee('Alpha wall night');

        $memberA->forceFill(['feed_token' => User::generateToken()])->save();
        $memberB->forceFill(['feed_token' => User::generateToken()])->save();

        $this->get(route('news.atom', $memberA))
            ->assertOk()
            ->assertSee('Alpha wall night', false);
        $this->get(route('news.rss', $memberA))
            ->assertOk()
            ->assertSee('Alpha wall night', false);
        $this->get(route('news.atom', $memberB))
            ->assertOk()
            ->assertDontSee('Alpha wall night', false);
    }

    public function test_the_member_news_list_has_no_team_filter_and_skips_drafts(): void
    {
        $alpha = Team::factory()->create(['name' => 'Alpha']);
        $bravo = Team::factory()->create(['name' => 'Bravo']);
        $member = User::factory()->teamMember($alpha)->create();
        $bravo->grant($member, MembershipRole::Member);

        NewsPost::factory()->forTeam($alpha)->published()->create(['title' => 'Alpha wall night']);
        NewsPost::factory()->forTeam($bravo)->draft()->create(['title' => 'Bravo draft']);

        Filament::setCurrentPanel('member');

        $this->actingAs($member)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertSee('Alpha wall night')
            ->assertDontSee('Bravo draft')
            ->assertDontSee('Filter by team');
    }

    public function test_first_publish_writes_inbox_and_opted_out_members_keep_the_row_and_feed(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        MailPreference::save($member, MailGroup::News, [
            MailChannel::NewsMail->value => '0',
        ]);
        $post = NewsPost::factory()->forTeam($team)->draft()->create([
            'title' => 'Quiet publish',
        ]);

        $this->actingAs($manager);
        $this->freezeTime();

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('publish');

        $this->assertSame(1, $member->notifications()->count());
        $this->assertSame(
            $manager->name.' published “Quiet publish” for '.$team->name.'.',
            $member->notifications()->first()->data['body']
        );
        $this->assertNotNull($member->notifications()->first()->data['url']);

        Mail::fake();
        app(CombinedDigest::class)->send($member);
        Mail::assertNothingOutgoing();

        Filament::setCurrentPanel('member');
        $member->forceFill(['feed_token' => User::generateToken()])->save();

        $this->actingAs($member)
            ->get(MemberNewsPostResource::getUrl('index', panel: 'member'))
            ->assertOk()
            ->assertSee('Quiet publish');
        $this->get(route('news.atom', $member))
            ->assertOk()
            ->assertSee('Quiet publish', false);
    }

    public function test_digest_sends_existing_news_mail_for_a_single_outstanding_item(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->draft()->create([
            'title' => 'Digest news',
        ]);

        $this->actingAs($manager);
        Livewire::test(ViewNewsPost::class, ['record' => $post->slug])
            ->callAction('publish');

        app(CombinedDigest::class)->send($member);

        $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertStringContainsString('Digest news', $message->getSubject());
        $this->assertNotNull($member->notifications()->first()->read_at);
    }

    public function test_news_mail_has_list_unsubscribe_and_signed_post_mutes_news_mail(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        $post = NewsPost::factory()->forTeam($team)->published()->create();
        MailPreference::tokenFor($member);

        $mail = new NewsPostMail($post, $member);
        $this->assertArrayHasKey('List-Unsubscribe', $mail->headers()->text);
        $this->assertSame('List-Unsubscribe=One-Click', $mail->headers()->text['List-Unsubscribe-Post']);

        $url = URL::signedRoute('mail-preferences.unsubscribe', [
            'user' => $member,
            'group' => MailGroup::News,
        ]);

        $this->get($url)->assertOk();
        $this->assertNull($member->fresh()->mail_preferences);

        $this->post($url)->assertOk();
        $this->assertFalse($member->fresh()->mail_preferences[MailChannel::NewsMail->value]);
    }

    public function test_regenerating_feed_token_stops_atom_rss_and_ics(): void
    {
        [$manager, $team, $member] = $this->newsActors();
        NewsPost::factory()->forTeam($team)->published()->create(['title' => 'Feed item']);
        $member->forceFill(['feed_token' => User::generateToken()])->save();

        $atom = route('news.atom', $member);
        $rss = route('news.rss', $member);
        $ics = route('booking.ics', $member);

        $this->get($atom)->assertOk()->assertSee('Feed item', false);
        $this->get($rss)->assertOk();
        $this->get($ics)->assertOk();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(Feeds::class)
            ->callAction('regenerate');

        $member->refresh();

        $this->get($atom)->assertUnauthorized();
        $this->get($rss)->assertUnauthorized();
        $this->get($ics)->assertUnauthorized();
        $this->get(route('news.atom', $member))->assertOk();
        $this->assertFalse(Feeds::shouldRegisterNavigation());
    }

    public function test_authenticated_panel_pages_emit_alternate_feed_links(): void
    {
        $member = User::factory()->teamMember()->create([
            'feed_token' => User::generateToken(),
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('rel="alternate"', false)
            ->assertSee('type="application/atom+xml"', false)
            ->assertSee('type="application/rss+xml"', false)
            ->assertSee('type="text/calendar"', false)
            ->assertSee(route('news.atom', $member), false)
            ->assertSee(route('booking.ics', $member), false)
            ->assertSee(route('booking.rota.ics', $member), false);

        Livewire::test(Feeds::class)
            ->callAction('regenerate');
        $member->refresh();

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee(route('news.atom', $member), false);

        auth()->logout();
        $this->flushSession();

        $this->get('/login')
            ->assertOk()
            ->assertDontSee('application/atom+xml', false);
    }

    public function test_due_news_job_is_scheduled_every_minute_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (object $event): bool => str_contains((string) $event->description, 'NotifyDueNewsPosts'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    /**
     * @return array{0: User, 1: Team, 2: User}
     */
    private function newsActors(): array
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();

        return [$manager, $team, $member];
    }
}
