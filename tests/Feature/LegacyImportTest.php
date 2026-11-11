<?php

namespace Tests\Feature;

use App\Enums\TeamFeature;
use App\LegacyImport\Support\LegacyFixtureSchema;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $leaderId;

    private string $memberId;

    private string $bookingId;

    private string $accessBookingId;

    private string $otherBookingId;

    private string $droppedBookingId;

    private string $emptySuspendedId;

    private string $keptSuspendedId;

    protected function setUp(): void
    {
        parent::setUp();

        $legacyPath = base_path('storage/framework/testing/legacy.sqlite');
        if (! is_dir(dirname($legacyPath))) {
            mkdir(dirname($legacyPath), 0777, true);
        }
        if (is_file($legacyPath)) {
            unlink($legacyPath);
        }
        touch($legacyPath);

        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => $legacyPath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('legacy');
        LegacyFixtureSchema::migrate('legacy');
        $this->seedLegacyFixture();
    }

    public function test_core_import_maps_users_bookings_quals_and_keys(): void
    {
        $this->artisan('legacy:import')->assertSuccessful();

        $team = Team::query()->where('slug', 'climbing')->first();
        $this->assertNotNull($team);

        $leader = User::query()->find($this->leaderId);
        $this->assertTrue($leader->is_system_admin);
        $this->assertTrue($leader->isCurrentMemberOf($team));

        $member = User::query()->find($this->memberId);
        $this->assertFalse($member->is_system_admin);

        $this->assertSame(1, Qualification::query()->where('user_id', $this->leaderId)->where('kind', 'scout_permit')->count());
        $this->assertSame(1, Qualification::query()->where('kind', 'girlguiding')->count());

        $climbingBooking = Booking::query()->find($this->bookingId);
        $this->assertSame('Climbing', $climbingBooking->activity->name);
        $this->assertSame('Scouts', $climbingBooking->audience->name);
        $this->assertTrue($climbingBooking->requires_lead_instructor);

        $accessBooking = Booking::query()->find($this->accessBookingId);
        $this->assertSame('Climbing Access', $accessBooking->activity->name);
        $this->assertSame('Tower Inspection', $accessBooking->activity_label);
        $this->assertSame('Team', $accessBooking->audience->name);

        $otherBooking = Booking::query()->find($this->otherBookingId);
        $this->assertNull($otherBooking->activity_id);
        $this->assertSame('Splash Camp', $otherBooking->activity_label);
        $this->assertFalse($otherBooking->requires_lead_instructor);
        $this->assertNull($otherBooking->lead_instructor_id);

        $this->assertDatabaseMissing('bookings', ['id' => $this->droppedBookingId]);

        $key = Key::query()->where('name', "Phill's Key")->first();
        $this->assertNotNull($key);
        $this->assertTrue($key->locks->pluck('name')->contains('Campsite Climbing'));

        $warden = Key::query()->where('name', "Phil's Warden Key")->first();
        $this->assertTrue($warden->locks->pluck('name')->contains('Campsite Warden'));

        $climbingLock = Lock::query()->where('name', 'Campsite Climbing')->firstOrFail();
        $wardenLock = Lock::query()->where('name', 'Campsite Warden')->firstOrFail();
        $this->assertTrue($wardenLock->covers()->whereKey($climbingLock->getKey())->exists());

        $this->assertSame(1, Comment::query()->where('commentable_id', $this->bookingId)->count());

        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $this->bookingId,
            'event' => 'created',
            'description' => 'created',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $this->bookingId,
            'event' => 'updated',
            'causer_id' => $this->leaderId,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $this->bookingId,
            'event' => 'attendance_updated',
            'causer_id' => $this->memberId,
        ]);
        $updated = DB::table('activity_log')
            ->where('subject_id', $this->bookingId)
            ->where('event', 'updated')
            ->first();
        $this->assertNotNull($updated);
        $this->assertSame(
            'confirmed',
            data_get(json_decode($updated->attribute_changes, true), 'attributes.status'),
        );

        $this->assertDatabaseMissing('users', ['id' => $this->emptySuspendedId]);
        $this->assertDatabaseHas('users', [
            'id' => $this->keptSuspendedId,
            'suspended' => false,
        ]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $this->keptSuspendedId]);
        $this->assertDatabaseMissing('qualifications', ['user_id' => $this->emptySuspendedId]);

        $this->assertDatabaseHas('news_posts', [
            'slug' => 'hello',
            'body' => 'See your [rota](/bookings?tab=rota) and [invites](/bookings?tab=invites).',
        ]);
    }

    public function test_deferred_importers_refuse_until_stories_ship(): void
    {
        $this->artisan('legacy:import', ['--only' => ['kit_checks']])
            ->assertFailed();
    }

    public function test_todos_import_as_tasks_and_enable_the_team_feature(): void
    {
        $this->artisan('legacy:import', ['--only' => ['catalog', 'todos']])->assertSuccessful();

        $this->assertDatabaseHas('tasks', [
            'summary' => 'Signage',
            'status' => 'needs-action',
            'priority' => 1,
        ]);

        $team = Team::query()->where('slug', 'climbing')->first();
        $this->assertNotNull($team);
        $this->assertTrue($team->featureEnabled(TeamFeature::Tasks));
    }

    public function test_verify_passes_after_import(): void
    {
        $this->artisan('legacy:import')->assertSuccessful();
        $this->artisan('legacy:verify')->assertSuccessful();
    }

    public function test_documents_copy_from_source_path(): void
    {
        Storage::fake('local');
        $sourceRoot = storage_path('framework/testing/legacy-upload');
        @mkdir($sourceRoot.'/upload/document', 0777, true);
        file_put_contents($sourceRoot.'/upload/document/risk.pdf', 'pdf-bytes');

        $this->artisan('legacy:import', [
            '--documents-path' => $sourceRoot,
        ])->assertSuccessful();

        $document = DB::table('documents')->first();
        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->file_path);
    }

    private function seedLegacyFixture(): void
    {
        $legacy = DB::connection('legacy');
        $now = now();

        $this->leaderId = (string) Str::ulid();
        $this->memberId = (string) Str::ulid();
        $this->emptySuspendedId = (string) Str::ulid();
        $this->keptSuspendedId = (string) Str::ulid();
        $this->bookingId = (string) Str::ulid();
        $this->accessBookingId = (string) Str::ulid();
        $this->otherBookingId = (string) Str::ulid();
        $this->droppedBookingId = (string) Str::ulid();

        $legacy->table('users')->insert([
            [
                'id' => $this->leaderId,
                'name' => 'Leader',
                'email' => 'leader@example.test',
                'email_verified_at' => $now,
                'password' => bcrypt('secret'),
                'timezone' => 'Europe/London',
                'role' => 'team-leader',
                'section' => 'adult',
                'accreditations' => '',
                'ical_token' => 'feed-token-1',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->memberId,
                'name' => 'Member',
                'email' => 'member@example.test',
                'email_verified_at' => $now,
                'password' => bcrypt('secret'),
                'timezone' => 'Europe/London',
                'role' => 'team-member',
                'section' => 'adult',
                'accreditations' => '',
                'ical_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->emptySuspendedId,
                'name' => 'Empty Suspended',
                'email' => 'empty-suspended@example.test',
                'email_verified_at' => $now,
                'password' => bcrypt('secret'),
                'timezone' => 'Europe/London',
                'role' => 'suspended',
                'section' => 'adult',
                'accreditations' => '',
                'ical_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->keptSuspendedId,
                'name' => 'Kept Suspended',
                'email' => 'kept-suspended@example.test',
                'email_verified_at' => $now,
                'password' => bcrypt('secret'),
                'timezone' => 'Europe/London',
                'role' => 'suspended',
                'section' => 'adult',
                'accreditations' => '',
                'ical_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $permitId = (string) Str::ulid();
        $ggId = (string) Str::ulid();

        $legacy->table('scout_permits')->insert([
            'id' => $permitId,
            'activity' => 'Climbing and Abseiling',
            'category' => 'Artificial Top Rope',
            'permit_type' => 'leadership',
            'restrictions' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacy->table('girlguiding_qualifications')->insert([
            'id' => $ggId,
            'scheme' => 'climbing',
            'level' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $emptyPermitId = (string) Str::ulid();
        $legacy->table('scout_permits')->insert([
            'id' => $emptyPermitId,
            'activity' => 'Climbing and Abseiling',
            'category' => 'Artificial Top Rope',
            'permit_type' => 'leadership',
            'restrictions' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacy->table('qualifications')->insert([
            [
                'id' => (string) Str::ulid(),
                'user_id' => $this->leaderId,
                'detail_type' => 'App\\Models\\ScoutPermit',
                'detail_id' => $permitId,
                'expires_on' => '2030-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'user_id' => $this->memberId,
                'detail_type' => 'App\\Models\\GirlguidingQualification',
                'detail_id' => $ggId,
                'expires_on' => '2028-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'user_id' => $this->emptySuspendedId,
                'detail_type' => 'App\\Models\\ScoutPermit',
                'detail_id' => $emptyPermitId,
                'expires_on' => '2029-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $legacy->table('bookings')->insert([
            [
                'id' => $this->bookingId,
                'start_at' => $now->copy()->addDay(),
                'end_at' => $now->copy()->addDay()->addHours(2),
                'status' => 'confirmed',
                'location' => 'Fox Coverts Campsite',
                'group_name' => '1st Example Scouts',
                'notes' => null,
                'activity' => 'Climbing',
                'lead_instructor_id' => $this->leaderId,
                'sequence' => 1,
                'timezone' => 'Europe/London',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->accessBookingId,
                'start_at' => $now->copy()->addDays(2),
                'end_at' => $now->copy()->addDays(2)->addHour(),
                'status' => 'confirmed',
                'location' => 'Fox Coverts Campsite',
                'group_name' => 'Fox Coverts Climbing Team',
                'notes' => null,
                'activity' => 'Tower Inspection',
                'lead_instructor_id' => $this->leaderId,
                'sequence' => 0,
                'timezone' => 'Europe/London',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->otherBookingId,
                'start_at' => $now->copy()->addDays(3),
                'end_at' => $now->copy()->addDays(3)->addHours(3),
                'status' => 'confirmed',
                'location' => 'Leicester Outdoor Pursuits Centre',
                'group_name' => 'Leicestershire Scouts',
                'notes' => 'Hosted externally',
                'activity' => 'Splash Camp',
                'lead_instructor_id' => $this->leaderId,
                'sequence' => 0,
                'timezone' => 'Europe/London',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $this->droppedBookingId,
                'start_at' => $now,
                'end_at' => $now->copy()->addHour(),
                'status' => 'cancelled',
                'location' => 'Fox Coverts Campsite',
                'group_name' => 'Testing',
                'notes' => null,
                'activity' => 'Testing',
                'lead_instructor_id' => null,
                'sequence' => 0,
                'timezone' => 'Europe/London',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $legacy->table('booking_user')->insert([
            [
                'booking_id' => $this->bookingId,
                'user_id' => $this->leaderId,
                'status' => 'accepted',
                'token' => str_repeat('a', 64),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'booking_id' => $this->bookingId,
                'user_id' => $this->emptySuspendedId,
                'status' => 'needs-action',
                'token' => str_repeat('b', 64),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'booking_id' => $this->bookingId,
                'user_id' => $this->keptSuspendedId,
                'status' => 'declined',
                'token' => str_repeat('c', 64),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $legacy->table('keys')->insert([
            [
                'id' => (string) Str::ulid(),
                'name' => "Phill's Key",
                'holder_id' => $this->leaderId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => "Phil's Warden Key",
                'holder_id' => $this->memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $legacy->table('news_posts')->insert([
            'id' => (string) Str::ulid(),
            'slug' => 'hello',
            'title' => 'Hello',
            'author_id' => $this->leaderId,
            'body' => 'See your [rota](/rota) and [invites](/invite).',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $legacy->table('documents')->insert([
            'id' => (string) Str::ulid(),
            'title' => 'Risk Assessment',
            'category' => 'Risk Assessment',
            'description' => 'Notes',
            'file_name' => 'risk.pdf',
            'file_path' => 'upload/document/risk.pdf',
            'file_size' => 9,
            'file_type' => 'application/pdf',
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $fieldChangeId = (string) Str::ulid();
        $commentChangeId = (string) Str::ulid();
        $attendanceChangeId = (string) Str::ulid();
        $legacy->table('changes')->insert([
            [
                'id' => $fieldChangeId,
                'changeable_id' => $this->bookingId,
                'changeable_type' => 'App\\Models\\Booking',
                'author_id' => $this->leaderId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $commentChangeId,
                'changeable_id' => $this->bookingId,
                'changeable_type' => 'App\\Models\\Booking',
                'author_id' => $this->leaderId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $attendanceChangeId,
                'changeable_id' => $this->bookingId,
                'changeable_type' => 'App\\Models\\Booking',
                'author_id' => $this->memberId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $legacy->table('change_comments')->insert([
            'id' => (string) Str::ulid(),
            'change_id' => $commentChangeId,
            'body' => 'Legacy comment',
        ]);
        $legacy->table('change_fields')->insert([
            'id' => (string) Str::ulid(),
            'change_id' => $fieldChangeId,
            'name' => 'status',
            'value' => 'confirmed',
        ]);
        $legacy->table('change_attendees')->insert([
            'id' => (string) Str::ulid(),
            'change_id' => $attendanceChangeId,
            'attendee_id' => $this->memberId,
            'attendee_status' => 'accepted',
            'attendee_comment' => null,
        ]);

        $legacy->table('todos')->insert([
            'id' => (string) Str::ulid(),
            'summary' => 'Signage',
            'description' => 'Replace trail signs',
            'location' => null,
            'priority' => 1,
            'status' => 'needs-action',
            'due_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'sequence' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacy->table('kit_checks')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $this->memberId,
            'checked_on' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $legacy->table('mail_logs')->insert([
            'id' => (string) Str::ulid(),
            'body' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
