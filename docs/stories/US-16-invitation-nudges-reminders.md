---
id: US-16
title: "Escalating Invitation Nudges & Booking Reminders"
status: done
area: communications
scope: extra
---

## Story

As an Activity Manager, I want the system to automatically send polite reminder notices to members with outstanding invitations and upcoming bookings to eliminate ambiguity from non-responders.

## Acceptance criteria

- Sends scheduled nudge emails for pending invitations and upcoming confirmed sessions.
- Dispatches respect user notification preference toggles.
- A reminder about one booking may set `References` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`, so it reappears beside the latest invite or update. Matching that mail's subject makes Gmail group them. A different subject is still allowed.

## Progress

Done via the daily [US-17](US-17-schedule-digest-changelog.md) / [US-67](US-67-member-notification-inbox.md) catch-up job. Invitation nudges (Needs action / Maybe) fire once per tier at 56 / 28 / 14 / 7 days before start, only when `booking_user.updated_at` (last invite or response) is at least half that tier’s days old — so a fresh Maybe does not get another nudge straight away — and stop after the week tier. Confirmed-session reminders fire once within 7 days for Can-help attendees. One due catch-up item alone reuses the invite/status mail shell with a tweaked intro and booking `References`; several items (or catch-up plus inbox/rota) use the combined digest with an Invitations section and a View invitations / View bookings link to the dashboard. Invitation nudges gate on `bookings.mail`; session reminders use `bookings.reminders` (child of All mail). Preferences footer; no `List-Unsubscribe`. Outstanding status matches [US-72](US-72-dashboard-needs-response.md).
