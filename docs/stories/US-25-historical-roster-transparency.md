---
id: US-25
title: "Historical Roster & Response Transparency"
status: partial
area: bookings
---

## Story

As an Activity Manager, I want to clearly see who has already been invited or has recorded a response when editing a booking roster later so that I can manage additions without confusion.

## Acceptance criteria

- When reopening a booking invitation screen post-creation, the interface distinguishes between uninvited members and those with existing invitation or response statuses (such as Going, Can't Go, or Pending).
- Prevents confusion over why certain members are hidden from the available uninvited pool.
- A decline records the start, end, and venue the person turned down. A later change to one of those can ask them again, because the session is no longer the one they refused. A morning decline can be available for the same session moved to the afternoon. A restore or edit that leaves that start, end, and venue unchanged leaves the decline in place. This applies to every decline. [US-15](US-15-inbound-calendar-response-parsing.md) separately records when the reply arrived by iMIP, which decides whether a cancellation is sent.

## Progress

The invite picker keeps someone who was already invited or has answered, shows that status, and does not let them be queued again. A decline still does not record the start, end, and venue they turned down, and changing that schedule does not make them invitable again.
