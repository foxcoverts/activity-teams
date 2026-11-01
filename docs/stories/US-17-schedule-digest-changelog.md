---
id: US-17
title: "Scheduled Schedule Digest & Changelog Notifications"
status: done
area: communications
scope: extra
---

## Story

As a team member, I want to receive an automated periodic digest email summarising schedule changes — featuring a changelog of recent updates followed by my full upcoming roster — so I can easily track shifts without being spammed by instant alerts.

## Acceptance criteria

- The digest job does not know the time of day. It sends whenever a person's rota changed since the snapshot it stored, or when [US-67](US-67-member-notification-inbox.md) has emailable inbox items. The scheduler runs it daily. A second run with nothing outstanding sends nothing. Turning `bookings.digest` off drops the rota section only and still advances the snapshot.
- A person is on the rota for a booking when their attendance is Can help. The digest includes only future bookings. The first snapshot is stored and does not email the whole rota.
- List additions, then removals, then the other future bookings already on the rota. An addition is a future booking they were not on at the last digest and are on now. A removal is a future booking they were on and are no longer on, including because it was cancelled or its time changed. When the same mail also includes inbox items, that section is above the rota.
- One emailable inbox item and no rota change sends that existing message. Several inbox items, or any emailable inbox item with a rota change, send one summary ("You have N new") plus the rota when it changed. A row read before the run is left out of both.
- Respects individual notification preference settings. `bookings.digest` off suppresses rota content only.
- The digest covers many bookings, so it does not set `References` to one booking's revision id. It does not send `List-Unsubscribe`.

A member who changes their own attendance does not get a per-booking mail; this digest is that notice. [US-27](US-27-manager-overridden-attendance.md) records the per-booking confirmation in the inbox when a manager makes the change. [US-16](US-16-invitation-nudges-reminders.md) reminds people who have not answered. There is no US-32.

## Progress

The rota section is the same job as [US-67](US-67-member-notification-inbox.md). `bookings.digest` is on the Notifications page. The job stores a snapshot per person and emails additions, removals, and the remaining future Can-help bookings. It does not set `References` or `List-Unsubscribe`.
