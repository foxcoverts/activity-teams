---
id: US-53
title: "Re-invite After Declined Session Changes"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want a member who declined an invitation to become eligible for a new invitation when the booking's start, end, or venue changes, so that a decline applies only to the session they actually turned down.

## Acceptance criteria

- When a member declines an invitation, add the booking's current start, end, and venue to a set of declined sessions on that attendance row.
- In the invitation picker, make the member eligible to be invited again when the booking's live start, end, and venue do not exactly match any retained declined session. This includes a session moved to a different time on the same day. Eligible declined people stay in the bottom picker (not auto-queued) and keep their Declined badge.
- Keep the decline in place when the booking is restored or edited but its live session still matches a retained declined session.
- If the member declines again after being invited to a changed session, add that new session to the set without forgetting earlier ones.
- Apply this behavior to every decline, regardless of how the response was recorded. Recording that a reply arrived by iMIP, and using that provenance to determine calendar cancellation behavior, remains the separate scope of [US-15](US-15-inbound-calendar-response-parsing.md).

## Progress

`booking_user.declined_sessions` stores a set of `{start_at, end_at, venue_id}` triples via `DeclinedSessions` / `AsDeclinedSessions`. Every decline path (`RespondToBookingAction`, `OverrideAttendanceAction`, `AddAttendeeAction`) adds the live session to that set. History survives re-invite and Accept; it is removed only when the attendee is detached. The invite picker, Keys invite, and Add-member options treat Declined + non-matching live session as re-invitable; opening auto-queue still skips them.
