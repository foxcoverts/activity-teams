---
id: US-79
title: "Task Assignees & iMIP"
status: done
area: communications
scope: extra
---

## Story

As a team manager, I want to assign one person to a task and keep their calendar in sync with iMIP, so that personal responsibility and updates travel with the assignee the way booking invites already do.

## Acceptance criteria

- Tasks support a **single assignee** (nullable) with full VTODO PARTSTAT (`needs-action`, `accepted`, `tentative`, `declined`, `in-process`, `completed`) and ATTENDEE on VTODO when inviting. Managers choose **Invite** (needs-action + calendar invite) or **Accepted** (accepted, no invite mail) in the same assign modal.
- Task STATUS `needs-action` displays as **Not started** in UI and mail; Filament section headings use **Pending task** for that status so the title reads cleanly.
- Assignee mail mirrors booking respond/view behaviour: open responses get **Accept** / **Decline** (no Maybe); already-answered get **View response**. Invite intros follow attendance (`You've been invited…` vs `You have been assigned…`). Update mail highlights real task field changes when present.
- Outbound and inbound task iMIP (`METHOD:REQUEST` / replies / refresh), reusing booking calendar-channel lessons where they apply.
- Optional per-task download or force-email of the current VTODO once iMIP exists.
- `RELATED-TO` remains a UID pointer only; do not embed booking VEVENTs inside a task REQUEST (or task VTODOs inside a booking REQUEST).
- **`rota.ics` includes assigned tasks** — VTODO components for tasks where the feed owner’s PARTSTAT is `accepted`, `tentative`, or `in-process`, alongside existing accepted/tentative booking VEVENTs.
- When the task is assigned, the assignee or a manager can complete it; unassigned tasks stay collaboratively completable by any team member.
- Assignee PARTSTAT and task STATUS stay aligned for start/complete (app and inbound).
- When task mail exists, add a `tasks` group to [US-61](US-61-notification-settings.md) and gate with `MailPreference::allows`.

## Progress

Done. Single-assignee model (not a multi-person guest list). Builds on [US-22](US-22-task-management.md) task model, UID/sequence, and subscription `tasks.ics` / `everything.ics` feeds. Booking iMIP patterns: [US-15](US-15-inbound-calendar-response-parsing.md), [US-76](US-76-booking-calendar-channel-and-force-send.md). Board: [#29](https://github.com/foxcoverts/activity-teams/issues/29).
