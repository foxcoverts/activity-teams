---
id: US-16
title: "Escalating Invitation Nudges & Booking Reminders"
status: not-started
area: communications
---

## Story

As an Activity Manager, I want the system to automatically send polite reminder notices to members with outstanding invitations and upcoming bookings to eliminate ambiguity from non-responders.

## Acceptance criteria

- Sends scheduled nudge emails for pending invitations and upcoming confirmed sessions.
- Dispatches respect user notification preference toggles.
- A reminder about one booking may set `References` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`, so it reappears beside the latest invite or update. Matching that mail's subject makes Gmail group them. A different subject is still allowed.
