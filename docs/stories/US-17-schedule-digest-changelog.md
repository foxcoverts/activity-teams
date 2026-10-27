---
id: US-17
title: "Scheduled Schedule Digest & Changelog Notifications"
status: not-started
area: communications
scope: extra
---

## Story

As a team member, I want to receive an automated periodic digest email summarising schedule changes — featuring a changelog of recent updates followed by my full upcoming roster — so I can easily track shifts without being spammed by instant alerts.

## Acceptance criteria

- Dispatches one digest a day. Send nothing when that person's rota is unchanged since the last digest.
- A person is on the rota for a booking when their attendance is Can help. The digest includes only future bookings.
- List additions, then removals, then the other future bookings already on the rota. An addition is a future booking they were not on at the last digest and are on now. A removal is a future booking they were on and are no longer on, including because it was cancelled or its time changed.
- Respects individual notification preference settings.
- The digest covers many bookings, so it does not set `References` to one booking's revision id.

A member who changes their own attendance does not get a per-booking mail; this digest is that notice. [US-27](US-27-manager-overridden-attendance.md) sends the per-booking confirmation when a manager makes the change. [US-16](US-16-invitation-nudges-reminders.md) reminds people who have not answered. There is no US-32.

## Progress

[US-61](US-61-notification-settings.md) reserves `bookings.digest`. Decide `hasSubject` vs `listUnsubscribe` here; gate with `MailPreference::allows`. Show the digest control on the Notifications page when this ships.
