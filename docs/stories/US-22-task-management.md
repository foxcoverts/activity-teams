---
id: US-22
title: "Team & Multi-Activity Task Management"
status: not-started
area: safety
scope: deferred
---

## Story

As an Activity Manager, I want to create, prioritise, assign, and track operational tasks (such as maintenance items and equipment checks) scoped to specific activity teams.

## Acceptance criteria

- Managers add tasks with titles, notes, start and due dates, and priorities.
- Generates standard iCalendar VTODO items.
- Scoped and filtered by activity teams with collaborative status updates.

## Progress

An activity team is a team in this app. Not built yet. When task mail exists, add a `tasks` group to the [US-61](US-61-notification-settings.md) catalog and gate sends with `MailPreference::allows`.
