# User stories

Backlog for Activity Teams. Each story is one file with `id`, `title`, `status`, `area`, and `scope` in the frontmatter.

Activity Teams is live on [climbfoxcoverts.co.uk](https://climbfoxcoverts.co.uk/). The cutover inventory was confirmed in a November 2026 live walk of climbing-team: every primary workflow maps to a story that is done, deferred, extra, or deliberately retired. Remaining work is product-owner acceptance on the live site ([acceptance-checklist.md](acceptance-checklist.md)), small parity fixes found there, and stretch extras only when they are clearly useful. Legacy code presence is evidence for review, not a product decision by itself; stretch extras are not silent go-live blockers.

Status is judged against this repository, not climbing-team:

- `done` — acceptance criteria met here
- `partial` — a slice exists; remaining criteria are still open
- `not-started` — not built in this app yet
- `retired` — deliberately dropped; covered elsewhere or no longer wanted

Scope is separate from implementation status:

- `essential` — central to the rewrite's purpose
- `required` — needed for operational relaunch
- `extra` — desirable, but not a relaunch requirement
- `deferred` — possible in the current system, but deliberately held back from relaunch

There is no US-32. Original ids are preserved.

## Roles

Roles live on different axes. A person can hold several at once (for example a **Manager** who is also **Lead Instructor** on one booking, or a **System admin** who also **manages** a team). **System admin does not imply team manager** — booking create/update, attendance, rollcall, and debrief complete need `manages($team)` or lead, not admin alone ([US-49](US-49-system-administration.md)).

### Account (site-wide)

| Role | What it is | Typical access |
| --- | --- | --- |
| **User** | Any authenticated account (`User` model). Base identity for profile, inbox, notifications, personal logbook. | Member panel. May also open Teams/Admin depending on the roles below. |
| **Oversight** | Account flag (`oversight`). Directory / compliance style access, not day-to-day team ops. | Admin panel (Users directory, qualifications read paths). Does **not** get a Teams tenant from oversight alone ([US-47](US-47-multiple-activity-teams.md)). |
| **System admin** | Account flag (`is_system_admin`). Site setup and break-glass. | Admin panel (teams, catalogs, keys, inbound mail). On Teams: can open any tenant; may manage equipment sets, members/roles, and Manager **bounce** triage **without** being a manager. **Cannot** create bookings or manage attendance/rollcall/debrief unless they also manage that team. |

### Team membership (`team_user.role`)

| Role | What it is | Typical access |
| --- | --- | --- |
| **Member** | Ordinary current membership on a team. | Sees confirmed team bookings; roster bookings they are on; Members panel tools; Teams panel for that team when they have a current tenant. |
| **Manager** | Team manager membership (`manages($team)`). Still a member of the team. | Bookings (any status), guest list, rollcall/debrief when allowed, equipment sets, news, Manager dashboard triage, booking **Reports**, member PII after [US-58](US-58-personal-data-viewing-acknowledgement.md). |
| **Parent** | Membership role in the schema (`MembershipRole::Parent`). Reserved / lightly used; treat like a non-manager team affiliation unless a story says otherwise. |

A person may belong to several teams with different membership roles on each.

### Booking-scoped (not membership)

| Role | What it is | Typical access |
| --- | --- | --- |
| **Attendee** | On a booking roster (`booking_user`) with an attendance status (Accepted, Tentative, Declined, Needs action, …). | `BookingPolicy::view` that booking; update **their own** attendance while the booking is not past/cancelled (`BookingAttendancePolicy::update` / `acceptsInvites()`). Does **not** by itself grant team-wide member or manager powers — roster access can outlive team membership (Teams tenant via past invite). Guest list UI still requires current team membership (or manager update). |
| **Lead instructor** | Person named on a booking (`lead_instructor_id`). Often also a team member/manager, but the lead powers are per booking. | Complete debrief from the booking header when allowed; Confirm attendees when allowed (action is lead\|manager; guest-list UI also needs current membership or manager). Lead contact windows ([US-18](US-18-lead-instructor-contact-access.md)); lead debrief nudges and lead dashboard “debriefs due”. Does **not** grant booking edit, attendance override, or other team-wide manager powers. |

### Related states (not roles)

These show up next to roles in UI and stories — do not treat them as the same thing:

- **Placeholder** — invited account shell before claim (no password / often no user email yet).
- **Suspended** / **Deleted** — account lifecycle ([US-48](US-48-suspend-and-restore-a-member.md), [US-57](US-57-member-account-privacy-controls.md)); blocks normal access.
- **Key holder** — holds a key that opens a relevant lock; a capability badge, not a membership role ([US-03](US-03-booking-venue-access-logistics.md)).

## Core Qualifications & Team Management

- [US-01 Member Qualification & Permit Management](US-01-member-qualification-permit-management.md) — `done`
- [US-02 Activity-Specific Qualification & Staffing Requirements](US-02-activity-specific-qualification-staffing.md) — `done`
- [US-66 Audience-Specific Qualification Requirements](US-66-audience-qualification-requirements.md) — `done`
- [US-03 Booking Venue Access & Logistics Planning](US-03-booking-venue-access-logistics.md) — `done`
- [US-04 Multi-Venue & Location-Based Management](US-04-multi-venue-location-management.md) — `done`
- [US-63 Venue Address and Coordinates](US-63-venue-address-and-coordinates.md) — `done`
- [US-05 Team Document & Resource Repository](US-05-team-document-repository.md) — `done`
- [US-06 User Role & Status Badges](US-06-user-role-status-badges.md) — `done`
- [US-07 Qualification Expiry Tracking](US-07-qualification-expiry-tracking.md) — `done`
- [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md) — `done`
- [US-49 System Administration](US-49-system-administration.md) — `done`
- [US-91 Admin Dashboard Triage](US-91-admin-dashboard-triage.md) — `done`
- [US-51 Pass a Key Ring](US-51-pass-a-key.md) — `done`
- [US-75: Participant Per-Team Microsites & Team-Hopping Navigation](US-75-participant-per-team-microsites.md) - `done`

## Bookings & Scheduling Lifecycle

- [US-08 Smart Booking Invitee Pre-Selection & Management](US-08-smart-booking-invitee-preselection.md) — `done`
- [US-09 Mandatory Lead Instructor Confirmation Validation](US-09-mandatory-lead-instructor-confirmation.md) — `done`
- [US-10 Lead Instructor Availability Change & Resignation Workflow](US-10-lead-instructor-resignation.md) — `done`
- [US-11 Lead Instructor Assignment Notifications](US-11-lead-instructor-assignment-notifications.md) — `done`
- [US-52 Lead Instructor Session Roster](US-52-lead-instructor-session-roster.md) — `done`
- [US-25 Historical Roster & Response Transparency](US-25-historical-roster-transparency.md) — `done`
- [US-53 Re-invite After Declined Session Changes](US-53-reinvite-after-declined-session-changes.md) — `done`
- [US-26 Filterable Roster Additions & Batch Management](US-26-filterable-roster-additions.md) — `done`
- [US-27 Manager-Overridden Attendance Updates](US-27-manager-overridden-attendance.md) — `done`
- [US-28 Booking Cancellation Reasons & Participant Notification](US-28-booking-cancellation-reasons.md) — `done`
- [US-37 Related Bookings & Multi-Day Event Linking](US-37-related-bookings-multi-day.md) — `done`
- [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md) — `not-started`
- [US-64 Catalog Venues and Private Booking Places](US-64-booking-other-venue.md) — `done`
- [US-68 Booking Other Activity](US-68-booking-other-activity.md) — `done`
- [US-77 Required Activity Label on Catalog Activity](US-77-required-activity-label.md) — `done`
- [US-78 Optional Lead Instructor on Booking](US-78-optional-lead-instructor.md) — `done`
- [US-81 Combined / Composite Catalog Activities](US-81-combined-catalog-activities.md) — `not-started`

## Communications, ICS & Calendar Integration

- [US-12 Public Booking Summary View](US-12-public-booking-summary.md) — `done`
- [US-88 In-app Calendar View](US-88-in-app-calendar-view.md) — `done`
- [US-13 Subscription ICS Export Feeds](US-13-subscription-ics-feeds.md) — `done`
- [US-65 ICS Precise Location](US-65-ics-precise-location.md) — `done`
- [US-14 Interactive Email RSVP Action Buttons](US-14-email-rsvp-action-buttons.md) — `done`
- [US-15 Inbound Calendar Response & Webhook Parsing](US-15-inbound-calendar-response-parsing.md) — `done`
- [US-76 Booking Calendar Channel & Force-Send Invite](US-76-booking-calendar-channel-and-force-send.md) — `done`
- [US-16 Escalating Invitation Nudges & Booking Reminders](US-16-invitation-nudges-reminders.md) — `done`
- [US-17 Scheduled Schedule Digest & Changelog Notifications](US-17-schedule-digest-changelog.md) — `done`
- [US-29 Booking Share Link Copy Action](US-29-booking-share-link-copy.md) — `retired`
- [US-30 Standard URL Sharing Capability](US-30-standard-url-sharing.md) — `done`
- [US-31 Open Graph Meta Tags for Link Previews](US-31-open-graph-link-previews.md) — `done`
- [US-45 Outgoing Email Delivery Tracking & Bounce Management](US-45-email-delivery-bounce-tracking.md) — `done`
- [US-80 Manager Bounce / Contact-Failure Triage](US-80-manager-bounce-triage-dashboard.md) — `done`
- [US-54 Team News Posts & Distribution](US-54-team-news-posts.md) — `done`
- [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md) — `done`
- [US-61 Notification Settings](US-61-notification-settings.md) — `done`
- [US-67 Member Notification Inbox](US-67-member-notification-inbox.md) — `done`
- [US-69 Member Dashboard: What Needs My Attention](US-69-member-dashboard.md) — `done`
- [US-70 Dashboard: My Bookings This Week](US-70-dashboard-this-week.md) — `done`
- [US-71 Dashboard: Unread Inbox Preview](US-71-dashboard-unread-inbox.md) — `done`
- [US-72 Dashboard: Responses Still Needed](US-72-dashboard-needs-response.md) — `done`
- [US-73 Dashboard: Next Booking I Lead](US-73-dashboard-next-led-booking.md) — `done`
- [US-74 Dashboard: Latest News](US-74-dashboard-latest-news.md) — `done`
- [US-62 Unmapped Calendar Reply Identity](US-62-unmapped-calendar-reply-identity.md) — `not-started`

## Personal Data & Account Privacy

- [US-56 Member Profile](US-56-member-profile.md) — `done`
- [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md) — `done`
- [US-58 Personal Data Viewing Acknowledgement](US-58-personal-data-viewing-acknowledgement.md) — `done`
- [US-59 Team Member Invitations](US-59-team-member-invitations.md) — `done`
- [US-60 Oversight & System Admin Invitations](US-60-oversight-system-admin-invitations.md) — `done`

## Audit Streams & Collaboration

- [US-38 Manager Centralised Activity Audit Feed](US-38-manager-activity-audit-feed.md) — `partial`
- [US-39 Member Booking Transparency & Audit Stream](US-39-member-booking-audit-stream.md) — `done`
- [US-40 Booking & Content Commenting Threads](US-40-booking-commenting-threads.md) — `done`
- [US-93 Task Commenting Threads](US-93-task-commenting-threads.md) — `done`

## Safety, Compliance & Operational Tools

- [US-18 Time-Bound Event Contact Access (Lead Instructors)](US-18-lead-instructor-contact-access.md) — `done`
- [US-19 On-The-Day Emergency & Contact Access (Participants)](US-19-participant-lead-contact-access.md) — `done`
- [US-20 Annual Profile & Emergency Contact Data Confirmation](US-20-annual-profile-confirmation.md) — `done`
- [US-21 Incident & Accident Reporting & Compliance](US-21-incident-accident-reporting.md) — `done`
- [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md) — `done`
- [US-22 Team & Multi-Activity Task Management](US-22-task-management.md) — `done`
- [US-79 Task Assignees & iMIP](US-79-task-assignees-and-imip.md) — `done`
- [US-23 Instructor Personal Kit Compliance Tracking](US-23-instructor-kit-compliance.md) — `not-started`
- [US-33 Kit Checker Inspection & Verification Workflow](US-33-kit-checker-inspection-workflow.md) — `not-started`
- [US-34 Expiring Kit Check Notifications & Alerts](US-34-expiring-kit-check-notifications.md) — `not-started`
- [US-35 Member Kit Self-Certification](US-35-member-kit-self-certification.md) — `not-started`
- [US-41 Intelligent Tentative Booking Triage & Readiness Dashboard](US-41-tentative-booking-triage.md) — `done`
- [US-42 Post-Session Debrief, Timing Nudges & Attendance Sign-Off](US-42-post-session-debrief.md) — `done`
- [US-43 Instructor Experience Logbook & Optional Personal Notes](US-43-instructor-experience-logbook.md) — `done`
- [US-92 External Logbook Sessions & Delete-Safe Snapshots](US-92-external-logbook-sessions.md) — `done`
- [US-44 Tentative Booking Manager Nudges & Reminder Alerts](US-44-tentative-booking-manager-nudges.md) — `done`
- [US-89 Manager Booking Volume Reports](US-89-manager-booking-volume-reports.md) — `done`
- [US-90 Manager Member Engagement Reports](US-90-manager-member-engagement-reports.md) — `done`

## Merchandise & Stash

Per-team shop for branded and personalised clothing and stash. Domain glossary and lifecycle: [merchandise-stash-domain.md](merchandise-stash-domain.md). Unrelated to booking equipment sets. All `extra`.

- [US-36 Merchandise & Stash Shop](US-36-merchandise-stash-requests.md) — `not-started` (umbrella)
- [US-24 Issued Merchandise Log](US-24-equipment-merchandise-allocation.md) — `not-started`
- [US-82 Merchandise Catalogue & Stock](US-82-merchandise-catalogue-and-stock.md) — `not-started`
- [US-83 Merchandise Member Orders](US-83-merchandise-member-orders.md) — `not-started`
- [US-84 Merchandise Order Approval & Discounts](US-84-merchandise-order-approval.md) — `not-started`
- [US-85 Merchandise Invoices & Treasurer Handoff](US-85-merchandise-invoices.md) — `not-started`
- [US-86 Manufacturer Orders & Goods In](US-86-merchandise-manufacturer-orders.md) — `not-started`
- [US-87 Merchandise Picking & Member Handover](US-87-merchandise-picking-and-handover.md) — `not-started`

## Relaunch scope decisions

- **Essential:** [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md) is the primary reason for the rewrite.
- **Operationally required:** [US-18 Lead Instructor Contact Access](US-18-lead-instructor-contact-access.md), [US-19 Participant Lead Contact Access](US-19-participant-lead-contact-access.md), [US-20 Annual Profile & Emergency Contact Data Confirmation](US-20-annual-profile-confirmation.md), [US-30 Standard URL Sharing](US-30-standard-url-sharing.md), [US-31 Open Graph Link Previews](US-31-open-graph-link-previews.md), [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md), [US-49 System Administration](US-49-system-administration.md), [US-54 Team News Posts & Distribution](US-54-team-news-posts.md), [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md), [US-56 Member Profile](US-56-member-profile.md), [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md), [US-58 Personal Data Viewing Acknowledgement](US-58-personal-data-viewing-acknowledgement.md), [US-61 Notification Settings](US-61-notification-settings.md), [US-67 Member Notification Inbox](US-67-member-notification-inbox.md), [US-76 Booking Calendar Channel & Force-Send Invite](US-76-booking-calendar-channel-and-force-send.md), [US-77 Required Activity Label on Catalog Activity](US-77-required-activity-label.md), and [US-78 Optional Lead Instructor on Booking](US-78-optional-lead-instructor.md). US-30/31 and US-54 are already done. News is published by managers, available in a combined member feed, and distributed directly to members, with an opt-out for direct delivery.
- **Extra:** US-16, US-17, US-24, US-34, US-35, US-36, US-38, US-41, US-42, US-43, [US-92 External Logbook Sessions](US-92-external-logbook-sessions.md), US-44, US-53, US-62, US-63, US-64, US-65, US-68, US-69 to US-74, [US-79 Task Assignees & iMIP](US-79-task-assignees-and-imip.md), [US-89 Manager Booking Volume Reports](US-89-manager-booking-volume-reports.md), [US-90 Manager Member Engagement Reports](US-90-manager-member-engagement-reports.md), [US-91 Admin Dashboard Triage](US-91-admin-dashboard-triage.md), and Merchandise & Stash children US-82 to US-87 (see [merchandise-stash-domain.md](merchandise-stash-domain.md)).
  - US-69 to US-74 are the member dashboard. Their scope is a proposal and is open for the product owner to decide. Admin triage is US-91.
  - US-29 is retired: address-bar sharing and the public booking summary are [US-30](US-30-standard-url-sharing.md) and [US-12](US-12-public-booking-summary.md).
  - US-38 is extra. Per-booking history is [US-39](US-39-member-booking-audit-stream.md) and stays done. The team-wide manager feed is not a relaunch requirement.
  - US-45 is done for sent/bounced tracking via ForwardEmail webhooks; delivered receipts stay deferred. Manager bounce triage is [US-80](US-80-manager-bounce-triage-dashboard.md) on the Manager dashboard.
  - US-63 is extra and done: catalog postal address and map pin. Bookings take `location` / `coords` from the venue. US-65 ICS precise location is done on the shared factory. US-64 catalog create, adoption lock, and private booking places, and US-68 Other activity and Activity name, are extra and done. US-13 stays done.
  - US-24 / US-36 and US-82–US-87 are Merchandise & Stash: documented for a later build, not relaunch scope. Not booking equipment sets.
- **Deferred:** [US-06 User Role & Status Badges](US-06-user-role-status-badges.md), [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md), [US-23 Instructor Kit Compliance](US-23-instructor-kit-compliance.md), and [US-33 Kit Checker Inspection](US-33-kit-checker-inspection-workflow.md). These are deliberately held back from relaunch.
