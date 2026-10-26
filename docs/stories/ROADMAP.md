# Activity Teams Relaunch Roadmap

## Goal and planning assumptions

Target a relaunch-ready Activity Teams application by February 2027. US-47, Multiple Activity Teams, is the essential purpose of the rewrite and is marked done. US-18, US-19, and US-48 are operationally required. Other stories should be pulled forward when they restore a legacy workflow or are an especially useful, affordable improvement; February is not intended to mean only those four stories.

Development time will include two clear weeks in December 2026 plus evenings and weekends, with other availability varying. The product owner considers a February 2027 relaunch probable, not guaranteed: work through the retained scope as capacity allows, review progress at checkpoints, and adjust scope or timing if needed. This is a dependency-ordered roadmap, not a promise of fixed weekly capacity. Do not assume every feature in the legacy repository must ship if the product owner confirms it is unused or deliberately retired.

Story `status` describes implementation in Activity Teams. Story `scope` describes relaunch priority. Keep scope unset until it is confirmed; a story being present in the legacy code is evidence for review, not by itself a product decision.

## Confirmed scope decisions

- **Essential:** [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md). It is the central reason for this rewrite.
- **Operationally required:** [US-18 Time-Bound Event Contact Access](US-18-lead-instructor-contact-access.md), [US-19 On-The-Day Emergency & Contact Access](US-19-participant-lead-contact-access.md), [US-20 Annual Profile & Emergency Contact Data Confirmation](US-20-annual-profile-confirmation.md), [US-30 Standard URL Sharing](US-30-standard-url-sharing.md), [US-31 Open Graph Link Previews](US-31-open-graph-link-previews.md), [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md), [US-49 System Administration](US-49-system-administration.md), [US-54 Team News Posts & Distribution](US-54-team-news-posts.md), [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md), [US-56 Member Profile](US-56-member-profile.md), [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md), [US-58 Personal Data Viewing Acknowledgement](US-58-personal-data-viewing-acknowledgement.md), and [US-61 Booking Notification Settings](US-61-booking-notification-settings.md).
- **Extra:** US-16, US-17, US-24, US-29, US-34, US-35, US-36, US-41, US-42, US-43, US-44, and US-53.
  - US-29 exists in the legacy application, but is not a relaunch priority.
  - US-45 is extra. Revisit it while outgoing mail is being implemented; include it if the chosen mail provider makes reliable delivery/bounce handling cheap or free.
- **Deferred:** [US-06 User Role & Status Badges](US-06-user-role-status-badges.md), [US-22 Task Management](US-22-task-management.md), and [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md). US-22's legacy task and VTODO workflow is not needed for relaunch.
- **Deferred:** [US-23 Instructor Kit Compliance](US-23-instructor-kit-compliance.md) and [US-33 Kit Checker Inspection](US-33-kit-checker-inspection-workflow.md). Kit checks are not needed for relaunch.

## Legacy comparison: known parity candidates

The legacy repository is `foxcoverts/climbing-team`. Its authenticated routes include booking, document, incident, key and key-transfer, kit-check, mail-log, news, qualification, and task-management workflows. Its booking model and routes also include related bookings and calendar feeds. These establish concrete parity candidates; confirm against actual team usage and the live system before treating every detail as a release gate.

| Legacy workflow | Story or follow-up | Current planning note |
|---|---|---|
| Shared documents | [US-05 Team Document & Resource Repository](US-05-team-document-repository.md) | Legacy has document management and trash routes. Not implemented in Activity Teams. |
| Incident reports | [US-21 Incident & Accident Reporting](US-21-incident-accident-reporting.md) | Legacy has an incident form and reporting flow. Safety and notification details need acceptance review. |
| Team tasks and iCalendar VTODO | [US-22 Task Management](US-22-task-management.md) | Legacy has task management and VTODO export. Explicitly deferred; not needed for relaunch. |
| Personal kit checks | [US-23 Instructor Kit Compliance](US-23-instructor-kit-compliance.md), [US-33 Kit Checker Inspection](US-33-kit-checker-inspection-workflow.md) | Legacy records annual checks and who performed them. Explicitly deferred; not needed for relaunch. |
| Passing a key ring | [US-51 Pass a Key Ring](US-51-pass-a-key-ring.md) | Legacy has key-transfer routes and notifications. Not implemented in Activity Teams. |
| Booking changes and comments | [US-38 Manager Activity Audit Feed](US-38-manager-activity-audit-feed.md), [US-39 Member Booking Audit Stream](US-39-member-booking-audit-stream.md), [US-40 Booking Commenting Threads](US-40-booking-commenting-threads.md) | Legacy records changes and comments. US-39 is done; US-38 is partial; US-40 describes a broader discussion experience. |
| Related bookings and calendar feeds | [US-13 Subscription ICS Export Feeds](US-13-subscription-ics-feeds.md), [US-37 Related Bookings & Multi-Day Event Linking](US-37-related-bookings-multi-day.md) | Legacy has related-booking routes and relationships. US-13 is partial; finish related-event export only after confirming US-37 behavior. |
| Account suspension | [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md) | Legacy has a suspended user state. Required for relaunch by decision. |
| News posts | [US-54 Team News Posts & Distribution](US-54-team-news-posts.md) | Required. Managers publish team news, members see a combined cross-team feed, and published news is also sent directly to relevant members. Members may opt out of direct distribution without losing feed access; agree the channels and opt-out mechanism during refinement. |
| Inbound email replies and iMIP failures | [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md) | Required. The legacy mail log helped the owner check inbound iMIP processing and debug failures, but the replacement must account for replies to other system emails too. Route recognized replies, retain unrouted or unprocessable messages in a restricted in-app review queue, and alert the system owner. Confirm the alert channel, retention period, content protections, and specific reply routes during refinement; a general-purpose mail-log UI is not required. |
| Member profile | [US-56 Member Profile](US-56-member-profile.md) | Required. The legacy member self-service profile edits name, email, phone, emergency contact, and timezone. Activity Teams stores those fields and managers can edit them; members need a self-service profile. Annual confirmation of contact details is [US-20](US-20-annual-profile-confirmation.md). |
| Booking notification preferences | [US-61 Booking Notification Settings](US-61-booking-notification-settings.md) | Required. Legacy has one team, so notification settings are global with per-booking overrides, not per-team settings. Members can manage `comment_mail`, `invite_mail`, `change_mail`, `confirm_mail`, and `cancel_mail`; `comment_mail` supports all, reply, leader, or none, while others are on/off. News delivery preferences are separate; members may opt out of direct distribution. |
| Member account security and data rights | [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md) | Required. Registration remains invitation-only. Members need password change/reset, a copy of their personal data, and a confirmed account-deletion process. After deletion, keep a named attendance/lead shell indefinitely (bookings have no participant ages, so a 3-year / age-21 cutoff cannot be applied); strip contact details after 30 days. |

The source review is not a substitute for checking the live system. Before implementing US-55, confirm the current inbound-mail address/path, message types, and operational owner. Its capture/routing and failure-alert behavior is required regardless of whether the existing general mail-log interface is replaced.

### Acceptance risks and Phase 0 decisions

Use these as risk-led acceptance areas, not as invented routes, models, response codes, or implementation designs. Write checks against Activity Teams' actual member and manager workflows and authorization rules.

- **Cross-team isolation (US-47):** Verify both permitted access and denied reads/changes across teams for bookings, team resources, and member data. Do not assume every record is team-owned: [US-01](US-01-member-qualification-permit-management.md) explicitly makes qualification records belong to the person. A manager of another current team edits the same qualification rows; the member can read their own records, and oversight can read but not change them. The owner confirmed incident reporters select the responsible team and that reports are accessible only to their reporter and managers of the selected team; see [US-21](US-21-incident-accident-reporting.md).
- **Personal-data viewing (US-58):** Before any user sees another person's personal details in any screen or workflow, require an explicit, unchecked-by-default acknowledgment that they will use the information strictly for team matters. Self-view is exempt. This is a disclosure safeguard in addition to authorization; it never grants access. Verify across profiles, rosters, contacts, and incidents (qualifications hold no personal details), and confirm that no details are rendered before acknowledgment.
- **Contact privacy windows (US-18/19):** Test eligible roles, the US-58 acknowledgment, opening and expiry boundaries, and unauthorized/system-admin access denial. Phone numbers have no start window and close at 23:59:59 on the booking end date. Emergency contact details are available from 00:00 on the start date through 23:59:59 on the end date. Leads see only people who accepted or are tentative; attendees see the lead. Those dates are taken in the booking's own timezone, which is required and not null in the database; examples such as “48 hours” or a particular HTTP status are not decisions.
- **Suspension (US-48):** Verify the member loses access and receives no booking/activity notifications, while the manager's explicitly permitted removal and profile actions remain available and silent. Assert application behavior rather than assuming session eviction or a particular response code.
- **Account and data rights (US-57):** Check invitation-only account creation, password change/reset, data access/export, confirmed deletion, privacy-notice acknowledgement, last-admin refusal, and the 30-day purge that leaves a named shell. Deletion does not erase attendance, qualifications, or key-ring assignment.
- **Inbound email (US-55):** Exercise recognized messages, legitimate messages that fail routing/processing, and invalid or unauthenticated webhook requests as distinct cases. The review queue and alert requirement concerns unprocessable or unrouted inbound mail; do not assume untrusted request payloads should be copied into that queue. Define authentication, safe diagnostics, retention, and alert behavior before acceptance checks are final.
- **News (US-54):** Verify manager publishing, cross-team feed isolation, direct email delivery to opted-in members, and no direct email to opted-out members while feed access remains. Preference controls, edit behavior, and delivery-failure behavior remain to be refined; news preferences are separate from US-61 booking-mail settings.
- **Calendar feeds (US-13):** Verify secure per-user feed access and the specified iCalendar content and identifiers. The owner confirmed that a member must be able to revoke or regenerate a token, and that the old URL must immediately stop granting access.

These decisions now have observable acceptance expectations in the stories. At minimum, release verification should check: (1) team-scoped reads and writes follow US-47's member/manager visibility rules; (2) member-held qualifications remain shared as US-01 defines, with oversight read-only; (3) US-58 blocks display of another person's personal details on every screen until the viewer explicitly acknowledges use strictly for team matters, while self-view is exempt and authorization remains enforced; (4) US-18/19 contact access works at the agreed inclusive booking-date boundaries and also requires the US-58 acknowledgment; (5) suspension blocks member access and suppresses notifications without blocking the manager exceptions in US-48; (6) US-21 incidents are associated with the reporter-selected team, visible only to the reporter and that team's managers, notify the team's managers and reporter, and require acknowledgment before revealing another person's details; (7) US-54 sends news by email to opted-in members while preserving feed access for opted-out members; (8) US-55 safely routes recognized mail and retains/alerts on unrouted or unprocessable mail without treating untrusted webhook payloads as legitimate messages; (9) US-13 feed URLs stop working after token revocation/regeneration; and (10) US-57 supports invitation-only accounts, password management, data access/export, and deletion subject to the controller-approved retention policy. Validate app behavior through the real routes and panels; do not prescribe a particular HTTP code or invented endpoint.

Gemini's proposed tests are prompts for these observable behaviors only. Its example endpoints, model/factory names, status codes, and code are not repository evidence and must not be copied without verifying the actual application.

The owner has reviewed the retained workflow list against the live legacy system and considers it complete. News distribution will use email, with an opt-out that does not remove feed access. The contact-access date window and incident-report team ownership/reader boundaries are decided; the booking timezone is the basis for those dates; post-submission report editing and retention details remain assigned for refinement. The account-deletion retention policy is recorded in US-57 (named shell kept indefinitely; contact details purged after 30 days).

## Phases

### Current deployment and migration baseline

The product owner confirmed IONOS hosting and an intention to replace the live `climbing-team` project, following the same deployment model: GitHub Actions deploys changes when they reach `main`. A new domain may be selected later. IONOS is currently configured to serve `climbfoxcoverts.co.uk` with PHP 8.3 and offers PHP 8.4 (recommended by IONOS) and PHP 8.5. The [legacy deploy workflow](https://github.com/foxcoverts/climbing-team/blob/main/.github/workflows/deploy.yml) uses GitHub Actions and SSH deployment to IONOS Webspace, with a maintenance-mode step and server-side deploy script. Verify the current workflow before reusing it: it invokes `.deploy/push.sh`, while the current `main` branch exposes `.deploy/post.sh`; that script explicitly invokes `php8.2-cli`. Align the CLI command with the PHP runtime selected for Activity Teams, and verify the chosen runtime against the application's dependencies before deployment.

Activity Teams is a new Laravel application. Its Sail compose file provides MariaDB and Mailpit for local development; `.env.example` defaults to SQLite, log-based outgoing mail, and database-backed queues. These are development defaults, not production decisions. ForwardEmail provides the inbound email API and is also available as a possible outgoing mail service. Confirm the integration details, deliverability, and whether using it for both directions is suitable before production configuration; reconsider US-45 during that outgoing-mail decision. The product owner wants to preserve as much legacy history as possible, but detailed migration planning is deliberately deferred until Activity Teams supports the retained workflows and its data model is sufficiently stable. At that point, assess whether the existing target database can be migrated into a workable state or a fresh database should be created and populated from the legacy database. Rehearse the chosen path and confirm backups and rollback before cutover. Production database settings and the final domain remain to be confirmed.

### Phase 0 — Confirm the cutover inventory

**Checkpoint: late October / early November 2026**

1. Walk through the live legacy workflows and mark each as keep for relaunch, retire, or undecided. Start with the parity candidates above. **Complete:** the owner has confirmed the retained workflow list is complete.
2. Compare each kept workflow with the current app and existing acceptance criteria. Add or split stories for real legacy gaps that have no story. The general legacy mail log is not required; US-55 captures the required inbound routing, retention, and failure-alert needs. **Complete for the known inventory:** US-54 through US-57 were added for identified gaps.
3. Confirmed relaunch scope: booking lifecycle, qualifications, venues/keys, documents, incidents, booking history/comments, related bookings/ICS, and member self-service profile are retained. US-06 and US-22 are deferred; US-30/31 and US-49 are required (US-30/31 are already done). US-23 and US-33 kit checks are deferred. Members may opt out of direct news distribution. Password changes, personal-data access/export, and account deletion are captured in required US-57; booking-mail preferences are US-61; registration remains invitation-only.
4. Record observable release checks for the retained workflows, including authorization boundaries and both member and manager experiences. The high-risk acceptance expectations are summarized above and detailed in the relevant stories; verify them through the actual application surfaces rather than presumed APIs.
5. Record the known operational approach and assign implementation follow-up: GitHub Actions deploys to IONOS when changes reach `main`; IONOS currently serves PHP 8.3; ForwardEmail provides inbound email and may be assessed for outbound. Verify runtime compatibility, adapt and test the deployment workflow, and confirm production mail/database configuration during implementation. The initial inbound-mail decision is to retain unmatched or failed messages in a restricted in-app review queue and alert the system owner. Do not detail or select the legacy data-migration strategy yet: revisit it once the current system is workable and its data model is stable enough to compare migrating the target database with importing legacy data into a fresh database. At that checkpoint, map what can be preserved and plan an import rehearsal, backups, and rollback. The current migration goal is to preserve as much history as can be safely mapped.

**Exit condition:** the owner has confirmed the retained live workflow inventory; relaunch scope is explicit; every retained workflow has a story or recorded acceptance checks; and remaining delivery/privacy risks have a named owner and a later checkpoint. Detailed data-migration design, production deployment verification, and controller retention policy review are assigned follow-ups, not prerequisites to freeze the Phase 0 inventory.

**Phase 0 status: complete.** The owner confirmed the live workflow inventory and relaunch scope, and the high-risk acceptance expectations are captured above and in the relevant stories. The following refinements remain assigned, but do not block Phase 0 closure:

- **Product owner:** decide post-submission report editing and retention details for US-21; finalize US-54 opt-out controls, post-edit behavior, and delivery-failure handling; and define US-55's webhook authentication, safe diagnostics, alert channel, and queue-retention process.
- **Product owner/controller:** US-57 retention is recorded (named shell kept indefinitely; contact details purged after 30 days). The live privacy notice is the in-app `/privacy` page; solicitor review of lawful bases remains with the product owner.
- **Implementation owner, during delivery:** verify ForwardEmail inbound authentication and routing, assess outbound mail and US-45 cost, and adapt/test the GitHub Actions-to-IONOS workflow for the selected PHP runtime.
- **Product owner and implementation owner, once Activity Teams is workable:** choose between migrating the target database and importing legacy data into a fresh database; rehearse the chosen approach with backups and rollback.

### Phase 1 — Secure the multi-team operating foundation

**First implementation priority after the inventory**

Start with the cross-cutting privacy acknowledgement, which also closes US-49's personal-data disclosure gap, then proceed through the foundation in small, testable slices:

1. **Done: implement US-58 across the existing personal-data surfaces.** A user must explicitly acknowledge use strictly for team matters before another person's personal details are displayed in any screen; self-view remains exempt. Ensure acknowledgment does not bypass authorization and no details render before it is made. Inventory affected screens and add focused coverage for representative member and manager surfaces.
2. **Done: close US-49 with US-58.** US-49 was kept `partial` until personal details in system-admin screens were gated by the acknowledgement; both are now `done`. The acknowledgement is not a separate admin-role prohibition: an otherwise authorized admin may proceed after confirming a legitimate team purpose.
3. **Done: regression-check US-47 before extending it.** Reuse and add only missing coverage for team isolation, multi-team membership, manager access, and the cross-team Members workspace. Avoid rebuilding behavior already covered by existing tests.
4. **Done: finish any remaining US-49 setup capability.** Verify system-admin team creation with a first manager, controlled activity/venue catalogs, team assignments, and that managers cannot create catalog entries from booking forms.
5. **Done: implement US-48 suspension and restoration**, including access denial, notification suppression, retained history, and the manager exceptions explicitly listed in the story.
6. **Done: implement US-18 and US-19 as separate, testable access paths.** Phone numbers close at the end of the booking end date; emergency details use the inclusive start-to-end date window; both require US-58 acknowledgment. The booking's own timezone determines the start and end dates (decided; it is required and not null in the database); test the boundary and denial rules in policy-level tests, not only presentation tests.
7. **Done: implement US-59 team member invitations** (placeholder named people, email invitation, claim into a new or existing account, suspended-email refusal). It must precede the US-56 profile work because account creation collects the contact details.
8. **Done: implement [US-60 Oversight & System Admin Invitations](US-60-oversight-system-admin-invitations.md)**, so that these super users can be added without needing to join a team first. Create User and the setup-account path were removed so later accounts are invitation-only.
9. **Done: implement US-56 member profile, US-20 annual confirmation, and US-57 account controls together:** self-service profile (name, contact, emergency, timezone), annual take-over of contact details, password change, personal-data export, soft-delete with a 30-day purge, and privacy-notice acknowledgement. Keep invitation-only registration.
10. US-06 role/status badges are deferred; verify the already-completed US-30/31 sharing behavior during release checks.

US-49 should precede changes that depend on controlled activity or venue catalogs. Phase 1 is not a single large delivery: complete each slice with the smallest meaningful tests and a manual check of its actual panel workflow before moving on.

**Phase 1 kickoff task (done):** implement US-58 on the member and team-manager detail screens that already display personal information, including the existing US-49 global and team-scoped member screens. First inventory those screens and their authorization tests; add regression coverage that distinguishes self-view, authorized non-self access after acknowledgment, and access denied by role/team regardless of acknowledgment. Then protect the disclosed personal fields, run focused feature tests, and manually check both member and manager workflows. This implements US-49's privacy criterion too; after US-58 and the remaining US-49 acceptance criteria are complete, mark US-49 done. Do not begin US-18/19 until the booking timezone is decided. US-57 retention is now recorded in that story.

### Phase 2 — Close booking, roster, and access gaps

1. **Done except the US-15→US-28 dependency:** remaining [US-10](US-10-lead-instructor-resignation.md) manager alerts and [US-11](US-11-lead-instructor-assignment-notifications.md) lead-responsibility copy. [US-28](US-28-booking-cancellation-reasons.md) cancellation reason, history, and subject are in place; emailing iMIP declines still waits on item 6.
2. **Done: [US-04 Multi-Venue Management](US-04-multi-venue-location-management.md) and [US-03 Booking Venue Access & Logistics](US-03-booking-venue-access-logistics.md).** Members can be marked and filtered by the keys the team needs. The booking Keys action is green when an accepted attendee holds a required key and amber otherwise; the modal groups holders by attending, invited, cannot make it, not invited, and in the cupboard. Other access arrangements stay manual.
3. **Done: [US-07 Qualification Expiry Tracking](US-07-qualification-expiry-tracking.md).** Current qualifications are green, those expiring within three months are amber, and expired ones are red on member qualification screens. Mountain Training stays uncoloured.
4. **Done: [US-51 Pass a Key Ring](US-51-pass-a-key-ring.md).** A holder or an interested team's manager can pass a ring to a current member of a team that needs those keys, or back to the cupboard. Mail skips the actor. The manage working list is Key rings.
5. Finish [US-37 Related Bookings](US-37-related-bookings-multi-day.md) before implementing related-event export in US-13.
6. Implement required [US-15](US-15-inbound-calendar-response-parsing.md) inbound calendar handling and US-55's broader inbound capture/routing and operator alerts. Ensure the US-15 iMIP decline provenance is in place before applying its special declined-attendee rule to US-28.
7. Implement [US-61 Booking Notification Settings](US-61-booking-notification-settings.md) global booking email defaults and per-booking overrides alongside the booking notification flows. Confirm precedence for mandatory operational notices.

**Dependency notes**

- US-49 → US-04 and venue-dependent parts of US-03.
- US-37 → related-event fields in US-13.
- US-15 → the US-28 behavior that depends on identifying an iMIP decline.
- US-55 should be implemented with whichever inbound-mail processing path is retained; it requires a safe destination for unrecognized replies and surfaced processing failures, not the historical mail-log UI.
- US-56 profile fields support US-18/19 contact access; US-61 per-booking preferences should be available to the corresponding booking mail flows.
- US-18/19 depend on contact data and a clearly defined authorization/window policy.
- US-27 currently references the US-17 digest in its acceptance criteria, even though US-17 is extra. Decide what member self-response notification behavior is required if the digest is deferred; do not let an optional digest silently become a relaunch blocker.

### Phase 3 — Deliver remaining retained team workflows

The product owner has confirmed these legacy workflows should be retained; the listed order remains adjustable based on implementation sizing and operational risk.

1. **Team communications:** implement required US-54 with manager publishing, a combined member feed, and email distribution. Refine opt-out controls, post-edit behavior, and delivery-failure handling before implementation.
2. **Safety and compliance:** implement required US-21 incident reporting.
3. **Team operations:** implement required US-05 document repository.
4. **Audit and collaboration:** complete required US-38 and implement US-40 comments. Keep the existing US-39 booking timeline behavior.
5. **Unrepresented legacy functions:** confirm any additional user-facing workflows identified in Phase 0. Do not rebuild the general-purpose legacy mail log; implement inbound routing and failure alerts in US-55.

If a retained workflow is large, ship it as a vertical slice: authorization and data model, manager workflow, member workflow, then migration/operational checks. Do not use a broad story label as a substitute for proving the acceptance criteria.

### Phase 4 — Release verification and February 2027 relaunch

Reserve time before cutover for integrated verification rather than adding features until the target date:

- Once the application is workable and its data model is stable, compare migrating the existing target database with importing legacy data into a fresh database. Choose the safer workable option, then rehearse it with representative data and verify team memberships, booking history, attendance, qualifications, keys, and contact/privacy fields.
- Run feature tests for critical member/manager paths, cross-team isolation, suspended accounts, consent and time-window enforcement, and booking email/calendar behavior.
- Manually test the responsive Livewire/Filament flows on the devices and browsers the team uses. Include keyboard and accessible disclosure behavior where relevant.
- Verify outgoing email delivery, scheduled jobs, calendar feeds, monitoring, backups, and rollback in the target environment.
- Have the product owner run an end-to-end acceptance pass against the agreed legacy workflow checklist.
- Cut over only when the must-have workflows and data checks pass; record any accepted differences from the legacy app.

**February target:** relaunch-ready is probable given the planned two clear weeks in December plus evenings and weekends, but is not a guarantee. Review progress and feasibility at checkpoints; if the retained scope cannot fit, adjust timing or agree explicit staging rather than silently dropping safety or access requirements.

## Stretch extras after parity sizing

The following are explicitly extra. Recommend selecting them only after Phase 0 estimates the core work and checks the real operational value:

- **Opportunistic UI:** US-29 is a small-looking share action, but is deliberately low priority.
- **Mail-provider dependent:** US-45 should be evaluated during outgoing-mail implementation. Do not add it if provider support requires a separate paid service or disproportionate operational work.
- **Related implementation batches:** US-16 and US-44 both involve reminders; US-17 adds stateful rota-digest behavior. Assess shared scheduling and notification infrastructure before splitting effort across them.
- **Kit extensions:** US-34 and US-35 build on the retained US-23/33 kit-check capability.
- **Session follow-on:** US-43 depends on the debrief/history behavior in US-42.
- **Other extras:** US-24, US-36, US-41, and US-53 should be ranked against user value and effort, not treated as implicit February commitments.

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
