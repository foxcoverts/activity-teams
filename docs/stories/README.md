# User stories

Backlog for Activity Teams. Each story is one file with `id`, `title`, `status`, `area`, and `scope` in the frontmatter.

See the [Relaunch Roadmap](ROADMAP.md) for the legacy-parity comparison, dependency phases, February 2027 target, and delivery approach.

Status is judged against this repository, not climbing-team:

- `done` — acceptance criteria met here
- `partial` — a slice exists; remaining criteria are still open
- `not-started` — not built in this app yet

Scope is separate from implementation status:

- `essential` — central to the rewrite's purpose
- `required` — needed for operational relaunch
- `extra` — desirable, but not a relaunch requirement
- `deferred` — possible in the current system, but deliberately held back from relaunch

There is no US-32. Original ids are preserved.

When an enum is shown in Filament, give it a label, and a color and icon when they help someone tell the values apart. `BookingStatus` is the pattern: confirmed is green with a check circle, tentative is yellow with a question-mark circle, and cancelled is red with an x circle. Give each enum this treatment when it next appears in the interface. Accreditation, role, section, attendee status, and the permit, qualification, and task enums from [climbing-team pull 303](https://github.com/foxcoverts/climbing-team/pull/303) are the reference.

## Core Qualifications & Team Management

- [US-01 Member Qualification & Permit Management](US-01-member-qualification-permit-management.md) — `done`
- [US-02 Activity-Specific Qualification & Staffing Requirements](US-02-activity-specific-qualification-staffing.md) — `done`
- [US-03 Booking Venue Access & Logistics Planning](US-03-booking-venue-access-logistics.md) — `not-started`
- [US-04 Multi-Venue & Location-Based Management](US-04-multi-venue-location-management.md) — `partial`
- [US-05 Team Document & Resource Repository](US-05-team-document-repository.md) — `not-started`
- [US-06 User Role & Status Badges](US-06-user-role-status-badges.md) — `partial`
- [US-07 Qualification Expiry Tracking](US-07-qualification-expiry-tracking.md) — `not-started`
- [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md) — `done`
- [US-49 System Administration](US-49-system-administration.md) — `done`
- [US-51 Pass a Key Ring](US-51-pass-a-key-ring.md) — `not-started`

## Bookings & Scheduling Lifecycle

- [US-08 Smart Booking Invitee Pre-Selection & Management](US-08-smart-booking-invitee-preselection.md) — `done`
- [US-09 Mandatory Lead Instructor Confirmation Validation](US-09-mandatory-lead-instructor-confirmation.md) — `done`
- [US-10 Lead Instructor Availability Change & Resignation Workflow](US-10-lead-instructor-resignation.md) — `partial`
- [US-11 Lead Instructor Assignment Notifications](US-11-lead-instructor-assignment-notifications.md) — `not-started`
- [US-52 Lead Instructor Session Roster](US-52-lead-instructor-session-roster.md) — `done`
- [US-25 Historical Roster & Response Transparency](US-25-historical-roster-transparency.md) — `done`
- [US-53 Re-invite After Declined Session Changes](US-53-reinvite-after-declined-session-changes.md) — `not-started`
- [US-26 Filterable Roster Additions & Batch Management](US-26-filterable-roster-additions.md) — `done`
- [US-27 Manager-Overridden Attendance Updates](US-27-manager-overridden-attendance.md) — `done`
- [US-28 Booking Cancellation Reasons & Participant Notification](US-28-booking-cancellation-reasons.md) — `partial`
- [US-37 Related Bookings & Multi-Day Event Linking](US-37-related-bookings-multi-day.md) — `not-started`
- [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md) — `not-started`

## Communications, ICS & Calendar Integration

- [US-12 Public Booking Summary View](US-12-public-booking-summary.md) — `done`
- [US-13 Subscription ICS Export Feeds](US-13-subscription-ics-feeds.md) — `partial`
- [US-14 Interactive Email RSVP Action Buttons](US-14-email-rsvp-action-buttons.md) — `done`
- [US-15 Inbound Calendar Response & Webhook Parsing](US-15-inbound-calendar-response-parsing.md) — `not-started`
- [US-16 Escalating Invitation Nudges & Booking Reminders](US-16-invitation-nudges-reminders.md) — `not-started`
- [US-17 Scheduled Schedule Digest & Changelog Notifications](US-17-schedule-digest-changelog.md) — `not-started`
- [US-29 Booking Share Link Copy Action](US-29-booking-share-link-copy.md) — `not-started`
- [US-30 Standard URL Sharing Capability](US-30-standard-url-sharing.md) — `done`
- [US-31 Open Graph Meta Tags for Link Previews](US-31-open-graph-link-previews.md) — `done`
- [US-45 Outgoing Email Delivery Tracking & Bounce Management](US-45-email-delivery-bounce-tracking.md) — `not-started`
- [US-54 Team News Posts & Distribution](US-54-team-news-posts.md) — `not-started`
- [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md) — `not-started`
- [US-61 Booking Notification Settings](US-61-booking-notification-settings.md) — `not-started`

## Personal Data & Account Privacy

- [US-56 Member Profile](US-56-member-profile.md) — `done`
- [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md) — `done`
- [US-58 Personal Data Viewing Acknowledgement](US-58-personal-data-viewing-acknowledgement.md) — `done`
- [US-59 Team Member Invitations](US-59-team-member-invitations.md) — `done`
- [US-60 Oversight & System Admin Invitations](US-60-oversight-system-admin-invitations.md) — `done`

## Audit Streams & Collaboration

- [US-38 Manager Centralised Activity Audit Feed](US-38-manager-activity-audit-feed.md) — `partial`
- [US-39 Member Booking Transparency & Audit Stream](US-39-member-booking-audit-stream.md) — `done`
- [US-40 Booking & Content Commenting Threads](US-40-booking-commenting-threads.md) — `not-started`

## Safety, Compliance & Operational Tools

- [US-18 Time-Bound Event Contact Access (Lead Instructors)](US-18-lead-instructor-contact-access.md) — `done`
- [US-19 On-The-Day Emergency & Contact Access (Participants)](US-19-participant-lead-contact-access.md) — `done`
- [US-20 Annual Profile & Emergency Contact Data Confirmation](US-20-annual-profile-confirmation.md) — `done`
- [US-21 Incident & Accident Reporting & Compliance](US-21-incident-accident-reporting.md) — `not-started`
- [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md) — `done`
- [US-22 Team & Multi-Activity Task Management](US-22-task-management.md) — `not-started`
- [US-23 Instructor Personal Kit Compliance Tracking](US-23-instructor-kit-compliance.md) — `not-started`
- [US-24 Equipment & Merchandise Allocation Tracking](US-24-equipment-merchandise-allocation.md) — `not-started`
- [US-33 Kit Checker Inspection & Verification Workflow](US-33-kit-checker-inspection-workflow.md) — `not-started`
- [US-34 Expiring Kit Check Notifications & Alerts](US-34-expiring-kit-check-notifications.md) — `not-started`
- [US-35 Member Kit Self-Certification](US-35-member-kit-self-certification.md) — `not-started`
- [US-36 Merchandise & Stash Requests](US-36-merchandise-stash-requests.md) — `not-started`
- [US-41 Intelligent Tentative Booking Triage & Readiness Dashboard](US-41-tentative-booking-triage.md) — `not-started`
- [US-42 Post-Session Debrief, Timing Nudges & Attendance Sign-Off](US-42-post-session-debrief.md) — `not-started`
- [US-43 Instructor Experience Logbook & Optional Personal Notes](US-43-instructor-experience-logbook.md) — `not-started`
- [US-44 Tentative Booking Manager Nudges & Reminder Alerts](US-44-tentative-booking-manager-nudges.md) — `not-started`

## Relaunch scope decisions

- **Essential:** [US-47 Multiple Activity Teams](US-47-multiple-activity-teams.md) is the primary reason for the rewrite.
- **Operationally required:** [US-18 Lead Instructor Contact Access](US-18-lead-instructor-contact-access.md), [US-19 Participant Lead Contact Access](US-19-participant-lead-contact-access.md), [US-20 Annual Profile & Emergency Contact Data Confirmation](US-20-annual-profile-confirmation.md), [US-30 Standard URL Sharing](US-30-standard-url-sharing.md), [US-31 Open Graph Link Previews](US-31-open-graph-link-previews.md), [US-48 Suspend and Restore a Member](US-48-suspend-and-restore-a-member.md), [US-49 System Administration](US-49-system-administration.md), [US-54 Team News Posts & Distribution](US-54-team-news-posts.md), [US-55 Inbound Email Routing & Failure Alerts](US-55-inbound-email-routing-and-alerts.md), [US-56 Member Profile](US-56-member-profile.md), [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md), [US-58 Personal Data Viewing Acknowledgement](US-58-personal-data-viewing-acknowledgement.md), and [US-61 Booking Notification Settings](US-61-booking-notification-settings.md). US-30/31 are already done. News must be published by managers, available in a combined member feed, and distributed directly to members, with an opt-out for direct delivery.
- **Extra:** US-16, US-17, US-24, US-29, US-34, US-35, US-36, US-41, US-42, US-43, US-44, and US-53.
  - US-29 has a UI action in the legacy climbing-team app, but not in Activity Teams; it is not a release priority.
  - US-45 is extra and should be reconsidered during outgoing-mail reimplementation; include delivery/bounce tracking if the mail provider makes it cheap or free.
- **Deferred:** [US-06 User Role & Status Badges](US-06-user-role-status-badges.md), [US-22 Team & Multi-Activity Task Management](US-22-task-management.md), [US-50 Invite Someone Who Is Not on the Team](US-50-invite-non-members.md), [US-23 Instructor Kit Compliance](US-23-instructor-kit-compliance.md), and [US-33 Kit Checker Inspection](US-33-kit-checker-inspection-workflow.md). These are deliberately held back from relaunch.

## Delivery approach

The product owner built the legacy system without AI and brings strong domain and implementation experience. Use AI assistance especially for bounded Livewire, front-end, and feature-test work, where it can accelerate implementation and test iteration. Keep product decisions, operational acceptance, and review of the resulting behavior with the product owner; use AI for execution and alternatives, not as the authority on legacy requirements.
