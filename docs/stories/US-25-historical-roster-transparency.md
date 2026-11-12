---
id: US-25
title: "Historical Roster & Response Transparency"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want to clearly see who has already been invited or has recorded a response when editing a booking roster later so that I can manage additions without confusion.

## Acceptance criteria

- When reopening a booking invitation screen post-creation, the interface distinguishes between uninvited members and those with existing invitation or response statuses (such as Going, Can't Go, or Pending).
- Prevents confusion over why certain members are hidden from the available uninvited pool.

## Progress

The invite picker keeps someone who was already invited or has answered, shows that status, and does not let them be queued again—except after a declined session no longer matches the live booking, when [US-53](US-53-reinvite-after-declined-session-changes.md) makes them manually re-invitable without auto-queuing.
