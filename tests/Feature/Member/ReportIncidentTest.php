<?php

namespace Tests\Feature\Member;

use App\Enums\Incident\Gender;
use App\Enums\Incident\Injury;
use App\Enums\Incident\MembershipType;
use App\Enums\MembershipRole;
use App\Filament\Member\Pages\ReportIncident;
use App\Mail\IncidentReport;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\IncidentReportFailureNotification;
use App\Support\Incident\IncidentReportData;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ReportIncidentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('member');
    }

    public function test_a_single_team_member_queues_one_report_to_their_team_managers_with_the_reporter_copied(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $suspendedManager = User::factory()->create(['suspended' => true]);
        $team->grant($suspendedManager, MembershipRole::Manager);
        $reporter = User::factory()->teamMember($team)->create([
            'name' => 'Report Author',
            'email' => 'reporter@example.test',
            'phone' => '07000111222',
        ]);
        Mail::fake();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->assertSet('data.team_id', $team->getKey())
            ->assertSet('data.reporter_name', $reporter->name)
            ->assertSet('data.reporter_email', $reporter->email)
            ->assertSet('data.reporter_phone', '+447000111222')
            ->fillForm($this->validReportData())
            ->call('submit')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect(ReportIncident::getUrl(panel: 'member'));

        Mail::assertQueued(IncidentReport::class, function (IncidentReport $mail) use ($manager, $reporter, $suspendedManager): bool {
            return $mail->hasTo($manager->email)
                && ! $mail->hasTo($suspendedManager->email)
                && $mail->hasCc($reporter->email)
                && $mail->hasReplyTo($reporter->email);
        });
        Mail::assertQueuedCount(1);
    }

    public function test_date_and_time_default_to_now_and_the_only_team_venue_prefills_the_location(): void
    {
        $this->travelTo('2026-11-03 07:16:23');

        $team = Team::factory()->create();
        $venue = Venue::factory()->create([
            'name' => 'Fox Coverts Hall',
            'street' => 'Main Road',
            'street2' => 'Rear entrance',
            'city' => 'Huntingdon',
            'region' => 'Cambridgeshire',
            'postcode' => 'PE28 2LS',
            'country' => 'GB',
        ]);
        $team->venues()->sync([$venue->getKey()]);
        $reporter = User::factory()->teamMember($team)->create();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->assertSet('data.date', '2026-11-03')
            ->assertSet('data.time', '07:16')
            ->assertSet('data.venue_id', (string) $venue->getKey())
            ->assertSet('data.location_name', 'Fox Coverts Hall, Main Road, Rear entrance, Huntingdon, Cambridgeshire, PE28 2LS, United Kingdom')
            ->assertSet('data.location_description', null)
            ->assertSee(__('Say where on site the incident happened.'));
    }

    public function test_venue_lookup_is_scoped_to_the_selected_team_and_updates_location_defaults(): void
    {
        $firstTeam = Team::factory()->create();
        $firstVenue = Venue::factory()->create(['name' => 'First Hall', 'street' => 'First Street']);
        $secondVenue = Venue::factory()->create(['name' => 'Second Hall', 'street' => 'Second Street']);
        $firstTeam->venues()->sync([$firstVenue->getKey(), $secondVenue->getKey()]);

        $secondTeam = Team::factory()->create();
        $teamVenue = Venue::factory()->create(['name' => 'Team Hall', 'street' => 'Team Street']);
        $secondTeam->venues()->sync([$teamVenue->getKey()]);

        $reporter = User::factory()->teamMember($firstTeam)->create();
        $secondTeam->grant($reporter, MembershipRole::Member);

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->set('data.team_id', (string) $firstTeam->getKey())
            ->assertSet('data.venue_id', null)
            ->assertSet('data.location_name', null)
            ->assertFormComponentActionExists('location_name', 'searchVenue')
            ->mountFormComponentAction('location_name', 'searchVenue')
            ->assertMountedActionModalSee(['Venue', 'First Hall', 'Second Hall'])
            ->assertMountedActionModalDontSee('Team Hall')
            ->callFormComponentAction('location_name', 'searchVenue', [
                'venue_id' => (string) $firstVenue->getKey(),
            ])
            ->assertSet('data.venue_id', (string) $firstVenue->getKey())
            ->assertSet('data.location_name', 'First Hall, First Street, United Kingdom')
            ->assertSet('data.location_description', null)
            ->set('data.team_id', (string) $secondTeam->getKey())
            ->assertSet('data.venue_id', (string) $firstVenue->getKey())
            ->assertSet('data.location_name', 'First Hall, First Street, United Kingdom')
            ->assertSet('data.location_description', null)
            ->mountFormComponentAction('location_name', 'searchVenue')
            ->assertMountedActionModalSee('Team Hall')
            ->assertMountedActionModalDontSee('First Hall');
    }

    public function test_an_automatic_venue_default_is_replaced_when_the_team_changes(): void
    {
        $firstTeam = Team::factory()->create();
        $firstVenue = Venue::factory()->create(['name' => 'First Hall', 'street' => 'First Street']);
        $firstTeam->venues()->sync([$firstVenue->getKey()]);

        $secondTeam = Team::factory()->create();
        $secondVenue = Venue::factory()->create(['name' => 'Second Hall', 'street' => 'Second Street']);
        $secondTeam->venues()->sync([$secondVenue->getKey()]);

        $reporter = User::factory()->teamMember($firstTeam)->create();
        $secondTeam->grant($reporter, MembershipRole::Member);

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->set('data.team_id', (string) $firstTeam->getKey())
            ->assertSet('data.venue_id', (string) $firstVenue->getKey())
            ->assertSet('data.location_name', 'First Hall, First Street, United Kingdom')
            ->assertSet('data.location_description', null)
            ->set('data.team_id', (string) $secondTeam->getKey())
            ->assertSet('data.venue_id', (string) $secondVenue->getKey())
            ->assertSet('data.location_name', 'Second Hall, Second Street, United Kingdom')
            ->assertSet('data.location_description', null);
    }

    public function test_manually_changed_location_details_are_preserved_when_the_team_changes(): void
    {
        $firstTeam = Team::factory()->create();
        $firstVenue = Venue::factory()->create(['name' => 'First Hall', 'street' => 'First Street']);
        $firstTeam->venues()->sync([$firstVenue->getKey()]);

        $secondTeam = Team::factory()->create();
        $secondVenue = Venue::factory()->create(['name' => 'Second Hall', 'street' => 'Second Street']);
        $secondTeam->venues()->sync([$secondVenue->getKey()]);

        $reporter = User::factory()->teamMember($firstTeam)->create();
        $secondTeam->grant($reporter, MembershipRole::Member);

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->set('data.team_id', (string) $firstTeam->getKey())
            ->assertSet('data.location_name', 'First Hall, First Street, United Kingdom')
            ->set('data.location_description', 'Custom entrance beside the car park.')
            ->set('data.team_id', (string) $secondTeam->getKey())
            ->assertSet('data.venue_id', (string) $firstVenue->getKey())
            ->assertSet('data.location_name', 'First Hall, First Street, United Kingdom')
            ->assertSet('data.location_description', 'Custom entrance beside the car park.');
    }

    public function test_autofocus_starts_on_the_team_field_or_on_the_date_when_the_team_is_preselected(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();

        $this->actingAs($reporter);
        $single = Livewire::test(ReportIncident::class)->html();

        $this->assertStringContainsString('autofocus="autofocus" id="form.date"', $single);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*autofocus/', $single);

        Team::factory()->create()->grant($reporter, MembershipRole::Member);
        $multiple = Livewire::test(ReportIncident::class)->html();

        $this->assertStringNotContainsString('autofocus="autofocus" id="form.date"', $multiple);
        $this->assertMatchesRegularExpression('/<select[^>]*autofocus/', $multiple);
    }

    public function test_a_member_with_multiple_teams_must_choose_the_responsible_team(): void
    {
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $firstManager = User::factory()->teamManager($firstTeam)->create();
        $secondManager = User::factory()->teamManager($secondTeam)->create();
        $reporter = User::factory()->teamMember($firstTeam)->create();
        $secondTeam->grant($reporter, MembershipRole::Member);
        Mail::fake();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->assertSet('data.team_id', null)
            ->fillForm([
                ...$this->validReportData(),
                'team_id' => $secondTeam->getKey(),
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        Mail::assertQueued(IncidentReport::class, function (IncidentReport $mail) use ($firstManager, $secondManager): bool {
            return $mail->hasTo($secondManager->email)
                && ! $mail->hasTo($firstManager->email);
        });
    }

    public function test_a_member_cannot_submit_a_report_for_a_team_they_no_longer_belong_to(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();
        $foreignTeam = Team::factory()->create();
        User::factory()->teamManager($foreignTeam)->create();
        $foreignTeam->grant($reporter, MembershipRole::Member);
        $reporter->memberships()
            ->where('team_id', $foreignTeam->getKey())
            ->update(['left_at' => now()]);
        Mail::fake();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm([
                ...$this->validReportData(),
                'team_id' => $foreignTeam->getKey(),
            ])
            ->call('submit')
            ->assertHasFormErrors(['team_id']);

        Mail::assertNothingOutgoing();
    }

    public function test_a_report_cannot_be_submitted_when_the_selected_team_has_no_active_managers(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();
        Mail::fake();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm($this->validReportData())
            ->call('submit')
            ->assertHasFormErrors(['team_id']);

        Mail::assertNothingOutgoing();
    }

    public function test_guests_users_without_a_current_team_and_suspended_users_cannot_access_the_page(): void
    {
        $url = ReportIncident::getUrl(panel: 'member');
        $memberWithoutTeams = User::factory()->create();
        $suspendedMember = User::factory()->create(['suspended' => true]);
        $team = Team::factory()->create();
        $team->grant($suspendedMember, MembershipRole::Member);
        $parent = User::factory()->create();
        $team->grant($parent, MembershipRole::Parent);

        $this->get($url)->assertRedirect('/login');

        $this->actingAs($memberWithoutTeams)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($suspendedMember)
            ->get($url)
            ->assertForbidden();

        $this->actingAs($parent)
            ->get($url)
            ->assertOk();
    }

    public function test_injured_reports_require_the_injury_details_and_response_fields(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm([
                ...$this->validReportData(),
                'injured' => 'yes',
            ])
            ->call('submit')
            ->assertHasFormErrors([
                'injured_name' => 'required',
                'injured_dob' => 'required',
                'injured_gender' => 'required',
                'injuries' => 'required',
                'emergency_services' => 'required',
                'hospital' => 'required',
                'first_aid' => 'required',
            ]);
    }

    public function test_injured_report_queues_enum_values_and_riddor_details(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $reporter = User::factory()->teamMember($team)->create();
        Mail::fake();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm([
                ...$this->validReportData(),
                'injured' => 'yes',
                'injured_name' => 'Injured person',
                'injured_dob' => '2000-01-01',
                'injured_gender' => Gender::Unknown->value,
                'injuries' => [Injury::Burns->value],
                'emergency_services' => 'no',
                'hospital' => 'no',
                'first_aid' => 'First aid was provided.',
            ])
            ->call('submit')
            ->assertHasNoFormErrors();

        Mail::assertQueued(IncidentReport::class, function (IncidentReport $mail) use ($manager): bool {
            return $mail->hasTo($manager->email)
                && $mail->report->injuredGender === Gender::Unknown
                && $mail->report->injuries === [Injury::Burns]
                && $mail->report->isRIDDOR();
        });
    }

    public function test_public_membership_reports_require_contact_phone_and_address_but_not_a_group_name(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm([
                ...$this->validReportData(),
                'membership_type' => MembershipType::YouthPublic->value,
                'group_name' => null,
            ])
            ->call('submit')
            ->assertHasFormErrors([
                'contact_phone' => 'required',
                'contact_address' => 'required',
            ]);
    }

    public function test_invalid_gb_reporter_phone_is_rejected(): void
    {
        $team = Team::factory()->create();
        $reporter = User::factory()->teamMember($team)->create();

        $this->actingAs($reporter);

        Livewire::test(ReportIncident::class)
            ->fillForm([
                ...$this->validReportData(),
                'reporter_phone' => 'not a phone number',
            ])
            ->call('submit')
            ->assertHasFormErrors(['reporter_phone']);
    }

    public function test_failed_report_delivery_notifies_only_active_verified_system_administrators(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $admin = User::factory()->systemAdmin()->create();
        $unverifiedAdmin = User::factory()->systemAdmin()->unverified()->create();
        $inactiveAdmin = User::factory()->systemAdmin()->inactive()->create();
        $suspendedAdmin = User::factory()->systemAdmin()->create(['suspended' => true]);
        Notification::fake();
        Log::spy();

        (new IncidentReport(IncidentReportData::from(
            $this->validReportData(),
            $team->name,
        )))->failed(new RuntimeException('SMTP unavailable'));

        Log::shouldHaveReceived('critical')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Incident report delivery failed after all retry attempts.'))
            ->once();
        Notification::assertSentTo($admin, IncidentReportFailureNotification::class);
        Notification::assertNotSentTo($manager, IncidentReportFailureNotification::class);
        Notification::assertNotSentTo($unverifiedAdmin, IncidentReportFailureNotification::class);
        Notification::assertNotSentTo($inactiveAdmin, IncidentReportFailureNotification::class);
        Notification::assertNotSentTo($suspendedAdmin, IncidentReportFailureNotification::class);

        $notification = new IncidentReportFailureNotification(
            IncidentReportData::from($this->validReportData(), $team->name),
            'SMTP unavailable',
        );
        $message = $notification->toMail($admin);
        $content = $message->render();

        $this->assertStringContainsString($team->name, $content);
        $this->assertStringContainsString('Report Author', $content);
        $this->assertStringContainsString('reporter@example.test', $content);
        $this->assertStringContainsString('SMTP unavailable', $content);
        $this->assertStringNotContainsString('A participant slipped on the path.', $content);
        $this->assertArrayNotHasKey('report', $message->viewData);
    }

    public function test_report_mail_retries_for_approximately_two_days_with_progressive_backoff(): void
    {
        $report = new IncidentReport(IncidentReportData::from(
            $this->validReportData(),
            'Fox Coverts',
        ));

        $this->assertGreaterThanOrEqual(9, $report->tries);
        $this->assertGreaterThanOrEqual(40 * 60 * 60, array_sum($report->backoff));
        $this->assertGreaterThanOrEqual(12 * 60 * 60, $report->backoff[array_key_last($report->backoff)]);
    }

    public function test_report_mail_escapes_user_provided_content_and_serializes_its_payload(): void
    {
        $data = $this->validReportData();
        $data['location_description'] = '<img src=x onerror=alert(1)>';
        $data['details'] = '<script>alert("unsafe")</script>';
        $data['reporter_name'] = 'Reporter <script>alert("unsafe")</script>';
        $report = IncidentReportData::from($data, 'Fox Coverts');

        $content = (new IncidentReport($report))->render();
        $restored = unserialize(serialize($report), ['allowed_classes' => true]);

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script>alert("unsafe")</script>', $content);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
        $this->assertInstanceOf(IncidentReportData::class, $restored);
        $this->assertSame($report->teamName, $restored->teamName);
        $this->assertSame($report->membershipType, $restored->membershipType);
    }

    public function test_riddor_warning_applies_to_hospital_or_non_minor_injuries(): void
    {
        $injuredData = [
            ...$this->validReportData(),
            'injured' => 'yes',
            'injured_name' => 'Injured person',
            'injured_dob' => '2000-01-01',
            'injured_gender' => 'Unknown',
            'injuries' => [Injury::Minor->value],
            'emergency_services' => 'no',
            'hospital' => 'no',
            'first_aid' => 'First aid was provided.',
        ];

        $minorInjury = IncidentReportData::from($injuredData, 'Fox Coverts');
        $hospitalized = IncidentReportData::from([
            ...$injuredData,
            'hospital' => 'yes',
        ], 'Fox Coverts');
        $nonMinorInjury = IncidentReportData::from([
            ...$injuredData,
            'injuries' => [Injury::Burns->value],
        ], 'Fox Coverts');

        $this->assertFalse($minorInjury->isRIDDOR());
        $this->assertTrue($hospitalized->isRIDDOR());
        $this->assertTrue($nonMinorInjury->isRIDDOR());
    }

    /**
     * @return array<string, mixed>
     */
    private function validReportData(): array
    {
        return [
            'date' => '2026-11-02',
            'time' => '14:30',
            'location_name' => 'Main wall',
            'location_description' => 'North side, beside the gate.',
            'injured' => 'no',
            'membership_type' => MembershipType::Scout->value,
            'group_name' => 'Fox Cubs',
            'contact_name' => 'Group leader',
            'damaged' => 'no',
            'details' => 'A participant slipped on the path.',
            'reporter_name' => 'Report Author',
            'reporter_email' => 'reporter@example.test',
            'reporter_phone' => '07000111222',
        ];
    }
}
