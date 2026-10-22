# Activity Teams Relaunch Roadmap

## Goal and planning assumptions

Target a relaunch-ready Activity Teams application by February 2027. US-47, Multiple Activity Teams, is the essential purpose of the rewrite and is marked done. US-18, US-19, and US-48 are operationally required. Other stories should be pulled forward when they restore a legacy workflow or are an especially useful, affordable improvement; February is not intended to mean only those four stories.

Development happens in variable spare-time windows, so this is a dependency-ordered roadmap, not a promise of fixed weekly capacity. Use the dates as checkpoints, size work after the legacy comparison, and replan at each checkpoint. Do not assume every feature in the legacy repository must ship if the product owner confirms it is unused or deliberately retired.

Story `status` describes implementation in Activity Teams. Story `scope` describes relaunch priority. Keep scope unset until it is confirmed; a story being present in the legacy code is evidence for review, not by itself a product decision.

## Confirmed scope decisions

- **Essential:** [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md). It is the central reason for this rewrite.
- **Operationally required:** [US-18 Time-Bound Event Contact Access](US-18-lead-instructor-contact-access.md), [US-19 On-The-Day Emergency & Contact Access](US-19-participant-lead-contact-access.md), and [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md).
- **Extra:** US-16, US-17, US-20, US-24, US-29, US-34, US-35, US-36, US-41, US-42, US-43, US-44, and US-53.
  - US-29 exists in the legacy application, but is not a relaunch priority.
  - US-45 is extra. Revisit it while outgoing mail is being implemented; include it if the chosen mail provider makes reliable delivery/bounce handling cheap or free.
- **Deferred:** [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md). It is possible in the legacy climbing-team app but is not needed for relaunch.

## Legacy comparison: known parity candidates

The legacy repository is `foxcoverts/climbing-team`. Its authenticated routes include booking, document, incident, key and key-transfer, kit-check, mail-log, news, qualification, and task-management workflows. Its booking model and routes also include related bookings and calendar feeds. These establish concrete parity candidates; confirm against actual team usage and the live system before treating every detail as a release gate.

| Legacy workflow | Story or follow-up | Current planning note |
|---|---|---|
| Shared documents | [US-05 Team Document & Resource Repository](US-05-team-document-repository.md) | Legacy has document management and trash routes. Not implemented in Activity Teams. |
| Incident reports | [US-21 Incident & Accident Reporting](US-21-incident-accident-reporting.md) | Legacy has an incident form and reporting flow. Safety and notification details need acceptance review. |
| Team tasks and iCalendar VTODO | [US-22 Task Management](US-22-task-management.md) | Legacy has task management and VTODO export. Not implemented in Activity Teams. |
| Personal kit checks | [US-23 Instructor Kit Compliance](US-23-instructor-kit-compliance.md), [US-33 Kit Checker Inspection](US-33-kit-checker-inspection-workflow.md) | Legacy records annual checks and who performed them. Confirm whether the new stories should be planned as one feature with separate slices. |
| Passing a key ring | [US-51 Pass a Key Ring](US-51-pass-a-key-ring.md) | Legacy has key-transfer routes and notifications. Not implemented in Activity Teams. |
| Booking changes and comments | [US-38 Manager Activity Audit Feed](US-38-manager-activity-audit-feed.md), [US-39 Member Booking Audit Stream](US-39-member-booking-audit-stream.md), [US-40 Booking Commenting Threads](US-40-booking-commenting-threads.md) | Legacy records changes and comments. US-39 is done; US-38 is partial; US-40 describes a broader discussion experience. |
| Related bookings and calendar feeds | [US-13 Subscription ICS Export Feeds](US-13-subscription-ics-feeds.md), [US-37 Related Bookings & Multi-Day Event Linking](US-37-related-bookings-multi-day.md) | Legacy has related-booking routes and relationships. US-13 is partial; finish related-event export only after confirming US-37 behavior. |
| Account suspension | [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md) | Legacy has a suspended user state. Required for relaunch by decision. |
| News posts | No story in the current index | Decide whether news is still used and, if so, add a story and scope decision before declaring parity. |
| Mail-log operations | No story precisely captures the legacy mail log | Do not conflate the legacy mail log with US-45 delivery/bounce tracking. Confirm whether the log is an operational requirement or an implementation detail to replace. |
| Profile and notification preferences | Partly related to US-18, US-19, and US-20 | Review the actual legacy profile fields and preference behavior; make separate stories for any required workflow not already covered. |

The source review is not a substitute for checking the live system. In particular, confirm whether there is a separately deployed inbound-calendar processing path before classifying [US-15 Inbound Calendar Response & Webhook Parsing](US-15-inbound-calendar-response-parsing.md) as parity work. The repository route inventory did not establish that behavior.

## Phases

### Phase 0 — Confirm the cutover inventory

**Checkpoint: late October / early November 2026**

1. Walk through the live legacy workflows and mark each as keep for relaunch, retire, or undecided. Start with the parity candidates above.
2. Compare each kept workflow with the current app and existing acceptance criteria. Add or split stories for real legacy gaps that have no story, especially news and mail-log operations if they remain in use.
3. Confirm which currently unscoped stories are parity requirements versus redesigns or extras. Prioritize the known ambiguities: US-03, US-04, US-06, US-07, US-13, US-15, US-21, US-22, US-23, US-33, US-37, US-38, US-40, US-49, and US-51.
4. Write observable acceptance checks for release-critical workflows, including authorization boundaries and both member and manager experiences.
5. Check the target deployment, data migration, email provider, backups, and rollback process early. This determines whether the February target is feasible and whether US-45 can be included cheaply.

**Exit condition:** the relaunch scope is explicit, every retained legacy workflow has a story or recorded acceptance checklist, and the largest delivery risks have an owner.

### Phase 1 — Secure the multi-team operating foundation

**First implementation priority after the inventory**

1. Preserve and regression-test the completed US-47 behavior: team isolation, multi-team membership, manager access, and cross-team member workspace.
2. Complete [US-49 System Administration](US-49-system-administration.md) where needed for team/catalog setup and controlled activities and venues.
3. Implement US-48 suspension and restoration, including access denial, notification suppression, retained history, and manager exceptions.
4. Implement US-18 and US-19 as separate, testable access paths. Establish the exact event windows, eligible booking roles, explicit consent behavior, and audit expectations before coding.
5. Close US-06 role/status gaps if they affect safe member and manager workflows.

US-49 should precede changes that depend on controlled activity or venue catalogs. Keep the US-18/19 privacy and timing rules explicit in policy-level tests, not only in presentation tests.

### Phase 2 — Close booking, roster, and access gaps

1. Finish the remaining behavior in [US-10 Lead Instructor Resignation](US-10-lead-instructor-resignation.md), [US-11 Lead Instructor Assignment Notifications](US-11-lead-instructor-assignment-notifications.md), and [US-28 Booking Cancellation Reasons](US-28-booking-cancellation-reasons.md).
2. Complete [US-04 Multi-Venue Management](US-04-multi-venue-location-management.md) and [US-03 Booking Venue Access & Logistics](US-03-booking-venue-access-logistics.md) if confirmed in the cutover inventory. Establish venue and key requirements before building derived access indicators.
3. Complete [US-07 Qualification Expiry Tracking](US-07-qualification-expiry-tracking.md) if expiry warnings are part of the retained credential workflow.
4. Implement [US-51 Pass a Key Ring](US-51-pass-a-key-ring.md) if the legacy key-transfer workflow remains in use.
5. Finish [US-37 Related Bookings](US-37-related-bookings-multi-day.md) before implementing related-event export in US-13.
6. Treat US-15 as an explicit scope decision. If it is retained, implement its inbound reply provenance before applying its special declined-attendee rule to US-28. Do not block the ordinary cancellation-reason workflow on an unconfirmed inbound-email feature.

**Dependency notes**

- US-49 → US-04 and venue-dependent parts of US-03.
- US-37 → related-event fields in US-13.
- US-15 → only the US-28 behavior that depends on identifying an iMIP decline.
- US-18/19 depend on contact data and a clearly defined authorization/window policy.
- US-27 currently references the US-17 digest in its acceptance criteria, even though US-17 is extra. Decide what member self-response notification behavior is required if the digest is deferred; do not let an optional digest silently become a relaunch blocker.

### Phase 3 — Restore retained legacy support workflows

Pull these forward according to the live-usage audit and operational risk; the listed order is a sensible starting point, not an assertion that they are all already approved as relaunch-required.

1. **Safety and compliance:** US-21 incident reporting; US-23/33 kit inspection and sign-off. Decide whether US-23 and US-33 are separate roles/slices in one capability.
2. **Team operations:** US-05 document repository and US-22 task management, including its VTODO behavior if still used.
3. **Audit and collaboration:** complete US-38 and implement the retained parts of US-40. Keep the existing US-39 booking timeline behavior.
4. **Unrepresented legacy functions:** implement or explicitly retire news, mail-log operations, and any required profile/notification-preference workflows identified in Phase 0.

If a retained workflow is large, ship it as a vertical slice: authorization and data model, manager workflow, member workflow, then migration/operational checks. Do not use a broad story label as a substitute for proving the acceptance criteria.

### Phase 4 — Release verification and February 2027 relaunch

Reserve time before cutover for integrated verification rather than adding features until the target date:

- Rehearse migration with representative data; verify team memberships, booking history, attendance, qualifications, keys, and contact/privacy fields.
- Run feature tests for critical member/manager paths, cross-team isolation, suspended accounts, consent and time-window enforcement, and booking email/calendar behavior.
- Manually test the responsive Livewire/Filament flows on the devices and browsers the team uses. Include keyboard and accessible disclosure behavior where relevant.
- Verify outgoing email delivery, scheduled jobs, calendar feeds, monitoring, backups, and rollback in the target environment.
- Have the product owner run an end-to-end acceptance pass against the agreed legacy workflow checklist.
- Cut over only when the must-have workflows and data checks pass; record any accepted differences from the legacy app.

**February target:** relaunch-ready. If Phase 0 sizing shows the retained legacy scope cannot fit alongside the required privacy and suspension work, reduce or stage the cutover scope explicitly rather than silently dropping safety or access requirements.

## Stretch extras after parity sizing

The following are explicitly extra. Recommend selecting them only after Phase 0 estimates the core work and checks the real operational value:

- **Opportunistic UI:** US-29 is a small-looking share action, but is deliberately low priority.
- **Mail-provider dependent:** US-45 should be evaluated during outgoing-mail implementation. Do not add it if provider support requires a separate paid service or disproportionate operational work.
- **Related implementation batches:** US-16 and US-44 both involve reminders; US-17 adds stateful rota-digest behavior. Assess shared scheduling and notification infrastructure before splitting effort across them.
- **Kit extensions:** US-34 and US-35 build on the retained US-23/33 kit-check capability.
- **Session follow-on:** US-43 depends on the debrief/history behavior in US-42.
- **Other extras:** US-20, US-24, US-36, US-41, and US-53 should be ranked against user value and effort, not treated as implicit February commitments.

US-50 remains deferred. US-47 and the operationally required stories take precedence over all extras.

## Working cadence and delegation

Capacity changes week to week, so plan in small, reviewable slices rather than fixed weekly story quotas.

1. At each weekly Cursor credit reset, choose one bounded implementation slice with acceptance criteria and a clear test target. The next reset noted during planning is **24 October 2026**.
2. Before spending credits, prepare the issue/story context, reproduction steps, expected UI behavior, and relevant tests. This makes a short work window useful even when spare time is limited.
3. Use Copilot/Cursor especially for Livewire and front-end behavior, feature-test setup, and iteration on failures—the areas where AI assistance can best complement the product owner's experience.
4. Keep product decisions, privacy choices, interpretation of live legacy behavior, release acceptance, credentials, and production operations with the product owner.
5. Use Gemini for independent review of story clustering, dependencies, and alternative sequencing. Treat it as a source of questions, not evidence about the legacy application.
6. For each slice, finish with tests, a manual UI check where applicable, and a short summary of changed behavior and remaining gaps before starting another slice.

## Evidence and references

- The story index is [README.md](README.md); it defines implementation status separately from scope.
- Legacy route inventory: [`climbing-team/routes/web.php`](https://github.com/foxcoverts/climbing-team/blob/main/routes/web.php).
- Legacy feature areas are evidenced by the `Document`, `Incident`, `KitCheck`, `Todo`, and `ChangeComment` models and the corresponding controllers, routes, and migrations in [`foxcoverts/climbing-team`](https://github.com/foxcoverts/climbing-team).
- Legacy source presence does not establish that a feature is actively used today. Phase 0 resolves that with the product owner and live-system review.
