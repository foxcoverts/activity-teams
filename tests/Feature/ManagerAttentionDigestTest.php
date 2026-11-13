<?php

namespace Tests\Feature;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\MembershipRole;
use App\Enums\TentativeTriage;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Mail\CombinedDigestMail;
use App\Mail\LeadNeededMail;
use App\Mail\ManagerAttentionNudgeMail;
use App\Models\Booking;
use App\Models\ManagerAttentionNotification;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use App\Support\BookingMailThread;
use App\Support\CombinedDigest;
use App\Support\MailPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagerAttentionDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_single_new_attention_item_sends_focused_mail_with_references(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $booking = $this->unstaffedBooking($team, days: 20);

        app(CombinedDigest::class)->send($manager);

        Mail::assertQueued(ManagerAttentionNudgeMail::class, function (ManagerAttentionNudgeMail $mail) use ($booking, $team): bool {
            return $mail->booking->is($booking)
                && $mail->triage === TentativeTriage::Unstaffed
                && $mail->team->is($team)
                && $mail->envelope()->subject === BookingMailThread::subject($booking, __('Unstaffed'))
                && $mail->headers()->referencesString() === BookingMailThread::headers($booking)->referencesString()
                && str_contains($mail->render(), 'Open Manager')
                && str_contains($mail->render(), ManagerDashboard::getUrl(panel: 'teams', tenant: $team))
                && ! str_contains($mail->render(), 'Manage notifications');
        });

        $this->assertDatabaseHas('manager_attention_notifications', [
            'user_id' => $manager->getKey(),
            'booking_id' => $booking->getKey(),
            'triage' => TentativeTriage::Unstaffed->value,
        ]);
    }

    public function test_new_plus_outstanding_sends_combined_sections(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $stale = $this->unstaffedBooking($team, days: 21, label: 'Already known');
        $fresh = $this->unstaffedBooking($team, days: 22, label: 'Brand new');

        ManagerAttentionNotification::query()->create([
            'user_id' => $manager->getKey(),
            'booking_id' => $stale->getKey(),
            'triage' => TentativeTriage::Unstaffed,
            'notified_at' => now()->subDay(),
        ]);

        app(CombinedDigest::class)->send($manager);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail): bool {
            $html = $mail->render();

            return $mail->outstanding === null
                && is_array($mail->attentionNew)
                && count($mail->attentionNew) === 1
                && str_contains($mail->attentionNew[0], 'Brand new')
                && is_array($mail->attentionOutstanding)
                && count($mail->attentionOutstanding) === 1
                && str_contains($mail->attentionOutstanding[0], 'Already known')
                && str_contains($html, 'Needs your attention')
                && str_contains($html, 'Still outstanding')
                && filled($mail->managerUrl)
                && str_contains($html, 'Open Manager')
                && ! str_contains($html, 'Manage notifications');
        });

        $this->assertSame(2, ManagerAttentionNotification::query()->where('user_id', $manager->getKey())->count());
        $this->assertTrue(
            ManagerAttentionNotification::query()
                ->where('booking_id', $fresh->getKey())
                ->where('triage', TentativeTriage::Unstaffed)
                ->exists(),
        );
    }

    public function test_only_outstanding_attention_sends_nothing(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $booking = $this->unstaffedBooking($team, days: 20);

        ManagerAttentionNotification::query()->create([
            'user_id' => $manager->getKey(),
            'booking_id' => $booking->getKey(),
            'triage' => TentativeTriage::Unstaffed,
            'notified_at' => now()->subDay(),
        ]);

        app(CombinedDigest::class)->send($manager);

        Mail::assertNothingOutgoing();
    }

    public function test_unstaffed_to_at_risk_reheadlines(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $booking = $this->unstaffedBooking($team, days: 16);

        app(CombinedDigest::class)->send($manager);

        Mail::assertQueued(ManagerAttentionNudgeMail::class, function (ManagerAttentionNudgeMail $mail): bool {
            return $mail->triage === TentativeTriage::Unstaffed;
        });

        $this->assertSame(
            TentativeTriage::Unstaffed,
            ManagerAttentionNotification::query()->where('booking_id', $booking->getKey())->first()?->triage,
        );

        Mail::fake();
        $this->travel(3)->days();

        app(CombinedDigest::class)->send($manager->fresh());

        Mail::assertQueued(ManagerAttentionNudgeMail::class, function (ManagerAttentionNudgeMail $mail) use ($booking): bool {
            return $mail->booking->is($booking)
                && $mail->triage === TentativeTriage::AtRisk
                && $mail->headers()->referencesString() === BookingMailThread::headers($booking)->referencesString();
        });

        $this->assertSame(
            TentativeTriage::AtRisk,
            ManagerAttentionNotification::query()->where('booking_id', $booking->getKey())->first()?->triage,
        );
    }

    public function test_lead_needed_inbox_is_not_emailable_and_needs_lead_goes_via_attention(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $booking = Booking::factory()->forTeam($team)->create([
            'activity_label' => 'Needs a lead',
            'lead_needed_at' => now(),
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'requires_lead_instructor' => true,
        ]);

        $manager->notify(new LeadNeededNotification($booking, null));

        app(CombinedDigest::class)->send($manager);

        Mail::assertNotQueued(LeadNeededMail::class);
        Mail::assertQueued(ManagerAttentionNudgeMail::class, function (ManagerAttentionNudgeMail $mail) use ($booking): bool {
            return $mail->booking->is($booking)
                && $mail->triage === TentativeTriage::NeedsLead;
        });

        $row = $manager->notifications()->where('type', LeadNeededNotification::class)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->read_at);
        $this->assertNull($row->digest_notified_at);
    }

    public function test_managers_have_independent_attention_stamps(): void
    {
        Mail::fake();

        $team = Team::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $team->grant($first, MembershipRole::Manager);
        $team->grant($second, MembershipRole::Manager);

        $booking = $this->unstaffedBooking($team, days: 20);

        app(CombinedDigest::class)->send($first);

        Mail::assertQueued(ManagerAttentionNudgeMail::class);
        $this->assertTrue(
            ManagerAttentionNotification::query()
                ->where('user_id', $first->getKey())
                ->where('booking_id', $booking->getKey())
                ->exists(),
        );
        $this->assertFalse(
            ManagerAttentionNotification::query()
                ->where('user_id', $second->getKey())
                ->exists(),
        );

        Mail::fake();
        app(CombinedDigest::class)->send($second);

        Mail::assertQueued(ManagerAttentionNudgeMail::class, function (ManagerAttentionNudgeMail $mail) use ($booking): bool {
            return $mail->booking->is($booking);
        });
    }

    public function test_attention_mail_is_always_on_when_booking_mail_is_off(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $this->unstaffedBooking($team, days: 20);

        MailPreference::save($manager, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
            MailChannel::BookingsUpdates->value => '0',
            MailChannel::BookingsDigest->value => '0',
        ]);

        app(CombinedDigest::class)->send($manager->fresh());

        Mail::assertQueued(ManagerAttentionNudgeMail::class);
    }

    public function test_send_all_includes_manager_attention_candidates(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $this->unstaffedBooking($team, days: 20);

        app(CombinedDigest::class)->sendAll();

        Mail::assertQueued(ManagerAttentionNudgeMail::class);
    }

    private function unstaffedBooking(Team $team, int $days, string $label = 'Unstaffed session'): Booking
    {
        return Booking::factory()->forTeam($team)->create([
            'activity_label' => $label,
            'start_at' => now()->addDays($days)->setTime(9, 30),
            'end_at' => now()->addDays($days)->setTime(11, 30),
            'requires_lead_instructor' => true,
            'lead_needed_at' => null,
        ]);
    }
}
