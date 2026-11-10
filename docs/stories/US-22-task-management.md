---
id: US-22
title: "Team & Multi-Activity Task Management"
status: done
area: safety
scope: required
---

## Story

As an Activity Manager, I want to create, prioritise, assign, and track operational tasks (such as maintenance items and equipment checks) scoped to specific activity teams.

## Acceptance criteria

- Managers add tasks with titles, notes, start and due dates, and priorities.
- Team-scoped filtering with collaborative status updates (members can mark complete / incomplete).
- Per-team Features flag controls Tasks navigation and create/attach surfaces without heavy extra queries when the team is already loaded.
- Tasks attach to bookings many-to-many (add existing / add new from a collapsed booking Tasks panel with a badge count).
- Todo-list style complete control to the left of the title (radio-style hollow / filled circle).
- Subscription feeds: `tasks.ics` (VTODO) and `everything.ics` (bookings VEVENT + tasks VTODO), sharing `feed_token`. VTODO includes `RELATED-TO` booking UIDs when linked.
- Legacy `todos` import into team tasks and enables the Climbing team Tasks feature.

## Progress

Done for the criteria above. Schema keeps UID/sequence for later calendar work. Assignees, task iMIP (`METHOD:REQUEST` / replies), per-task ICS download, and `rota.ics` including assigned tasks are [US-79](US-79-task-assignees-and-imip.md). When task mail exists, add a `tasks` group to the [US-61](US-61-notification-settings.md) catalog and gate sends with `MailPreference::allows`.
