---
id: US-79
title: "Task Assignees & iMIP"
status: not-started
area: communications
scope: extra
---

## Story

As a team manager, I want to assign people to tasks and keep their calendars in sync with iMIP, so that personal responsibility and updates travel with the assignee the way booking invites already do.

## Acceptance criteria

- Tasks support assignees (guest-list style participation), with ATTENDEE on VTODO when inviting.
- Outbound and inbound task iMIP (`METHOD:REQUEST` / replies), reusing booking calendar-channel lessons where they apply.
- Optional per-task download or force-email of the current VTODO once iMIP exists.
- `RELATED-TO` remains a UID pointer only; do not embed booking VEVENTs inside a task REQUEST (or task VTODOs inside a booking REQUEST).
- **`rota.ics` includes assigned tasks** — VTODO components for tasks assigned to the feed owner, alongside existing accepted/tentative booking VEVENTs (personal “what I am on the hook for”).
- When task mail exists, add a `tasks` group to [US-61](US-61-notification-settings.md) and gate with `MailPreference::allows`.

## Progress

Not started. Builds on [US-22](US-22-task-management.md) task model, UID/sequence, and subscription `tasks.ics` / `everything.ics` feeds. Booking iMIP patterns: [US-15](US-15-inbound-calendar-response-parsing.md), [US-76](US-76-booking-calendar-channel-and-force-send.md).
