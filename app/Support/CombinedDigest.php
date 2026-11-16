<?php

namespace App\Support;

use App\Enums\ScheduleNudgeTier;
use App\Enums\TentativeTriage;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Mail\BookingInvitationNudgeMail;
use App\Mail\BookingSessionReminderMail;
use App\Mail\CombinedDigestMail;
use App\Mail\ManagerAttentionNudgeMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\InboxNotification;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Concerns\RecordsInbox;
use App\Notifications\LeadNeededNotification;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class CombinedDigest
{
    public function __construct(
        private RotaDigest $rota,
        private InboundReviewNudge $inbound,
        private BookingCatchUp $catchUp,
        private ManagerAttentionCatchUp $managerAttention,
        private DebriefCatchUp $debriefs,
        private LogbookCatchUp $logbook,
    ) {}

    public function sendAll(): void
    {
        $this->inbound->record();

        $ids = InboxNotification::query()->outstanding()->pluck('notifiable_id')
            ->merge($this->rota->candidateUserIds())
            ->merge($this->catchUp->candidateUserIds())
            ->merge($this->managerAttention->candidateUserIds())
            ->merge($this->debriefs->candidateUserIds())
            ->merge($this->logbook->candidateUserIds())
            ->unique()
            ->filter()
            ->values();

        User::query()->whereKey($ids->all())->each(function (User $user): void {
            $this->send($user);
        });
    }

    public function send(User $user): void
    {
        $rotaByTeam = $this->rota->deltasByTeam($user);
        $outstanding = $user->notifications()->outstanding()->get();
        $emailable = $outstanding->filter(fn (InboxNotification $row): bool => $this->emailable($user, $row));
        $inboxByTeam = $this->partitionInboxByTeam($emailable);
        $globalInbox = $inboxByTeam['global'];
        $teamInbox = $inboxByTeam['byTeam'];
        $globalInboxCount = $globalInbox->count();
        $totalEmailableCount = $emailable->count();
        $nudges = $this->catchUp->dueInvitationNudges($user);
        $reminders = $this->catchUp->dueSessionReminders($user);
        $catchUpCount = $nudges->count() + $reminders->count();
        $attention = $this->managerAttention->due($user);
        $attentionNew = $attention['new'];
        $attentionOutstanding = $attentionNew->isEmpty()
            ? collect()
            : $attention['outstanding'];
        $attentionNewCount = $attentionNew->count();
        $debriefFocused = $this->debriefs->dueFocusedNudges($user);
        $debriefFocusedCount = $debriefFocused->count();
        $debriefSummary = $this->debriefs->outstandingSummary($user);
        $logbookFocused = $this->logbook->dueFocusedNudges($user);
        $logbookFocusedCount = $logbookFocused->count();
        $logbookSummary = $this->logbook->outstandingSummary($user);
        $rotaChanged = $rotaByTeam->isNotEmpty();

        if (
            $totalEmailableCount === 0
            && ! $rotaChanged
            && $catchUpCount === 0
            && $attentionNewCount === 0
            && $debriefFocusedCount === 0
            && $logbookFocusedCount === 0
        ) {
            return;
        }

        $teamIdsWithContent = $this->teamIdsWithContent(
            $rotaByTeam,
            $nudges,
            $reminders,
            $attentionNew,
            $debriefFocused,
            $logbookFocused,
            $teamInbox,
        );

        if (
            $totalEmailableCount === 1
            && ! $rotaChanged
            && $catchUpCount === 0
            && $attentionNewCount === 0
            && $debriefFocusedCount === 0
            && $logbookFocusedCount === 0
        ) {
            $row = $emailable->first();

            if ($row instanceof InboxNotification) {
                $this->sendExisting($user, $row);
            }

            return;
        }

        $teams = Team::query()
            ->whereKey($teamIdsWithContent->all())
            ->orderBy('name')
            ->get();

        $soleTeamContent = $teamIdsWithContent->count() === 1 && $globalInbox->isEmpty();
        $sentTeamDigest = false;

        foreach ($teams as $team) {
            $teamId = (string) $team->getKey();
            $teamRota = $rotaByTeam->get($teamId);
            $teamNudges = $this->forBookingTeam($nudges, $teamId);
            $teamReminders = $this->forBookingTeam($reminders, $teamId);
            $teamCatchUpCount = $teamNudges->count() + $teamReminders->count();
            $teamAttentionNew = $this->forItemTeam($attentionNew, $teamId);
            $teamAttentionOutstanding = $teamAttentionNew->isEmpty()
                ? collect()
                : $this->forItemTeam($attentionOutstanding, $teamId);
            $teamDebriefFocused = $this->forBookingTeam($debriefFocused, $teamId);
            $teamLogbookFocused = $this->forBookingTeam($logbookFocused, $teamId);
            $teamDebriefSummary = $this->forBookingTeam($debriefSummary, $teamId);
            $teamLogbookSummary = $this->forBookingTeam($logbookSummary, $teamId);
            $teamInboxRows = $teamInbox->get($teamId, collect());
            $teamInboxCount = $teamInboxRows->count();
            $teamRotaChanged = $teamRota !== null;

            if ($soleTeamContent && (string) $teamIdsWithContent->first() === $teamId) {
                if (
                    $teamInboxCount === 0
                    && ! $teamRotaChanged
                    && $teamCatchUpCount === 0
                    && $teamAttentionNew->count() === 0
                    && $teamDebriefFocused->count() === 1
                    && $teamLogbookFocused->count() === 0
                ) {
                    $item = $teamDebriefFocused->first();
                    $this->debriefs->sendFocused($user, $item['booking'], $item['week']);
                    $sentTeamDigest = true;

                    continue;
                }

                if (
                    $teamInboxCount === 0
                    && ! $teamRotaChanged
                    && $teamCatchUpCount === 0
                    && $teamAttentionNew->count() === 0
                    && $teamDebriefFocused->count() === 0
                    && $teamLogbookFocused->count() === 1
                ) {
                    $item = $teamLogbookFocused->first();
                    $this->logbook->sendFocused($user, $item['booking'], $item['attendance']);
                    $sentTeamDigest = true;

                    continue;
                }

                if (
                    $teamInboxCount === 0
                    && ! $teamRotaChanged
                    && $teamCatchUpCount === 1
                    && $teamAttentionNew->count() === 0
                    && $teamDebriefFocused->count() === 0
                    && $teamLogbookFocused->count() === 0
                ) {
                    $this->sendFocusedCatchUp($user, $teamNudges, $teamReminders);
                    $sentTeamDigest = true;

                    continue;
                }

                if (
                    $teamInboxCount === 0
                    && ! $teamRotaChanged
                    && $teamCatchUpCount === 0
                    && $teamAttentionNew->count() === 1
                    && $teamAttentionOutstanding->isEmpty()
                    && $teamDebriefFocused->count() === 0
                    && $teamLogbookFocused->count() === 0
                ) {
                    $this->sendFocusedAttention($user, $teamAttentionNew);
                    $sentTeamDigest = true;

                    continue;
                }
            }

            $teamHasOtherDigestContent = $teamInboxCount > 0
                || $teamRotaChanged
                || $teamCatchUpCount > 0
                || $teamAttentionNew->isNotEmpty()
                || $teamDebriefFocused->isNotEmpty()
                || $teamLogbookFocused->isNotEmpty();
            $includeDebriefSummary = $teamDebriefSummary->isNotEmpty() && $teamHasOtherDigestContent;
            $includeLogbookSummary = $teamLogbookSummary->isNotEmpty() && $teamHasOtherDigestContent;
            $unreadRemainder = $teamInboxCount === 0
                ? 0
                : max(0, $user->unreadNotifications()->count() - $outstanding->count());
            $rotaPayload = $teamRotaChanged
                ? [
                    'changed' => true,
                    'additions' => $teamRota['additions'],
                    'removals' => $teamRota['removals'],
                    'remaining' => $teamRota['remaining'],
                ]
                : null;

            Mail::to($user)->send(new CombinedDigestMail(
                outstanding: $teamInboxCount === 0 ? null : $teamInboxCount,
                unreadRemainder: $unreadRemainder,
                rota: $rotaPayload,
                invitationNudges: $teamNudges->isEmpty() ? null : $teamNudges->pluck('label')->all(),
                sessionReminders: $teamReminders->isEmpty() ? null : $teamReminders->pluck('label')->all(),
                attentionNew: $teamAttentionNew->isEmpty() ? null : $teamAttentionNew->pluck('label')->all(),
                attentionOutstanding: $teamAttentionOutstanding->isEmpty() ? null : $teamAttentionOutstanding->pluck('label')->all(),
                debriefsDue: $includeDebriefSummary ? $teamDebriefSummary->pluck('label')->all() : null,
                logbookDue: $includeLogbookSummary ? $teamLogbookSummary->pluck('label')->all() : null,
                inboxUrl: InboxResource::getUrl(panel: 'member'),
                dashboardUrl: Dashboard::getUrl(panel: 'member'),
                managerUrl: $this->managerUrl($teamAttentionNew->merge($teamAttentionOutstanding)),
                team: $team,
            ));

            if ($teamInboxCount > 0) {
                $teamInboxRows->each(function (InboxNotification $row): void {
                    $row->forceFill(['digest_notified_at' => now()])->save();
                });
            }

            $this->stampCatchUp($teamNudges, $teamReminders);
            $this->managerAttention->stamp($user, $teamAttentionNew->merge($teamAttentionOutstanding));

            foreach ($teamDebriefFocused as $item) {
                $this->debriefs->mark($item['booking'], $item['week']);
            }

            foreach ($teamLogbookFocused as $item) {
                $this->logbook->mark($item['attendance']);
            }

            $sentTeamDigest = true;
        }

        if ($globalInbox->isEmpty()) {
            return;
        }

        if ($globalInboxCount === 1 && ! $sentTeamDigest && $teamIdsWithContent->isEmpty()) {
            $row = $globalInbox->first();

            if ($row instanceof InboxNotification) {
                $this->sendExisting($user, $row);
            }

            return;
        }

        $unreadRemainder = max(0, $user->unreadNotifications()->count() - $outstanding->count());

        Mail::to($user)->send(new CombinedDigestMail(
            outstanding: $globalInboxCount,
            unreadRemainder: $unreadRemainder,
            rota: null,
            invitationNudges: null,
            sessionReminders: null,
            attentionNew: null,
            attentionOutstanding: null,
            debriefsDue: null,
            logbookDue: null,
            inboxUrl: InboxResource::getUrl(panel: 'member'),
            dashboardUrl: Dashboard::getUrl(panel: 'member'),
            managerUrl: null,
        ));

        $globalInbox->each(function (InboxNotification $row): void {
            $row->forceFill(['digest_notified_at' => now()])->save();
        });
    }

    /**
     * @param  Collection<int, InboxNotification>  $emailable
     * @return array{global: Collection<int, InboxNotification>, byTeam: Collection<string, Collection<int, InboxNotification>>}
     */
    private function partitionInboxByTeam(Collection $emailable): array
    {
        $global = collect();
        $byTeam = collect();

        foreach ($emailable as $row) {
            $teamId = $row->teamId();

            if ($teamId === null) {
                $global->push($row);

                continue;
            }

            if (! $byTeam->has($teamId)) {
                $byTeam->put($teamId, collect());
            }

            $byTeam->get($teamId)->push($row);
        }

        return [
            'global' => $global,
            'byTeam' => $byTeam,
        ];
    }

    /**
     * @param  Collection<string, array{team: Team, changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}>  $rotaByTeam
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: ScheduleNudgeTier, label: string}>  $nudges
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>  $reminders
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $attentionNew
     * @param  Collection<int, array{booking: Booking, label: string, week: bool}>  $debriefFocused
     * @param  Collection<int, array{booking: Booking, attendance: BookingAttendance, label: string}>  $logbookFocused
     * @param  Collection<string, Collection<int, InboxNotification>>  $teamInbox
     * @return Collection<int, string>
     */
    private function teamIdsWithContent(
        Collection $rotaByTeam,
        Collection $nudges,
        Collection $reminders,
        Collection $attentionNew,
        Collection $debriefFocused,
        Collection $logbookFocused,
        Collection $teamInbox,
    ): Collection {
        return collect()
            ->merge($rotaByTeam->keys())
            ->merge($nudges->map(fn (array $item): ?string => $this->bookingTeamId($item['booking'])))
            ->merge($reminders->map(fn (array $item): ?string => $this->bookingTeamId($item['booking'])))
            ->merge($attentionNew->map(fn (array $item): string => (string) $item['team']->getKey()))
            ->merge($debriefFocused->map(fn (array $item): ?string => $this->bookingTeamId($item['booking'])))
            ->merge($logbookFocused->map(fn (array $item): ?string => $this->bookingTeamId($item['booking'])))
            ->merge($teamInbox->keys())
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $items
     * @return Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>
     */
    private function forItemTeam(Collection $items, string $teamId): Collection
    {
        return $items->filter(fn (array $item): bool => (string) $item['team']->getKey() === $teamId)->values();
    }

    /**
     * @param  Collection<int, array{booking: Booking, label: string, ...}>  $items
     * @return Collection<int, array{booking: Booking, label: string, ...}>
     */
    private function forBookingTeam(Collection $items, string $teamId): Collection
    {
        return $items->filter(fn (array $item): bool => $this->bookingTeamId($item['booking']) === $teamId)->values();
    }

    private function bookingTeamId(Booking $booking): ?string
    {
        return filled($booking->team_id) ? (string) $booking->team_id : null;
    }

    /**
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: ScheduleNudgeTier, label: string}>  $nudges
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>  $reminders
     */
    private function sendFocusedCatchUp(User $user, Collection $nudges, Collection $reminders): void
    {
        if ($nudges->count() === 1) {
            $item = $nudges->first();
            Mail::to($user)->send(new BookingInvitationNudgeMail($item['booking'], $user));
            $this->catchUp->markInvitationNudge($item['attendance'], $item['tier']);

            return;
        }

        $item = $reminders->first();
        Mail::to($user)->send(new BookingSessionReminderMail($item['booking'], $user));
        $this->catchUp->markSessionReminder($item['attendance']);
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $items
     */
    private function sendFocusedAttention(User $user, Collection $items): void
    {
        $item = $items->first();
        Mail::to($user)->send(new ManagerAttentionNudgeMail($item['booking'], $item['triage'], $item['team']));
        $this->managerAttention->stamp($user, $items);
    }

    /**
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: ScheduleNudgeTier, label: string}>  $nudges
     * @param  Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>  $reminders
     */
    private function stampCatchUp(Collection $nudges, Collection $reminders): void
    {
        foreach ($nudges as $item) {
            $this->catchUp->markInvitationNudge($item['attendance'], $item['tier']);
        }

        foreach ($reminders as $item) {
            $this->catchUp->markSessionReminder($item['attendance']);
        }
    }

    /**
     * @param  Collection<int, array{booking: Booking, triage: TentativeTriage, label: string, team: Team}>  $items
     */
    private function managerUrl(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        return ManagerDashboard::getUrl(panel: 'teams', tenant: $items->first()['team']);
    }

    private function emailable(User $user, InboxNotification $row): bool
    {
        if ($row->type === LeadNeededNotification::class) {
            return false;
        }

        if ($row->isInAppOnly()) {
            return false;
        }

        if ($row->isAlwaysEmailable()) {
            return true;
        }

        $channel = $row->mailChannel();

        if ($channel === null) {
            return false;
        }

        return MailPreference::allows($user, $channel, $row->preferenceSubject());
    }

    private function sendExisting(User $user, InboxNotification $row): void
    {
        $type = $row->type;

        if (! is_string($type) || ! class_exists($type) || ! method_exists($type, 'fromInbox')) {
            return;
        }

        $notification = $type::fromInbox($row->data);

        if (! $notification instanceof Notification || ! in_array(RecordsInbox::class, class_uses_recursive($notification), true)) {
            return;
        }

        $message = $notification->toMail($user);

        if ($message instanceof Mailable) {
            Mail::to($user)->send($message);
        } else {
            app(MailChannel::class)->send($user, $notification);
        }

        $row->forceFill([
            'read_at' => now(),
            'digest_notified_at' => now(),
            'outbound_message_id' => (string) Str::uuid(),
        ])->save();
    }
}
