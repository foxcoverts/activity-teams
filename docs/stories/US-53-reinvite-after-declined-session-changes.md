---
id: US-53
title: "Re-invite After Declined Session Changes"
status: not-started
area: bookings
---

## Story

As an Activity Manager, I want a member who declined an invitation to become eligible for a new invitation when the booking's start, end, or venue changes, so that a decline applies only to the session they actually turned down.

## Acceptance criteria

- When a member declines an invitation, record the booking's start, end, and venue with that decline.
- In the invitation picker, make the member eligible to be invited again when the booking's current start, end, or venue differs from the values recorded with their decline. This includes a session moved to a different time on the same day.
- Keep the decline in place when the booking is restored or edited but its start, end, and venue still match the declined session.
- If the member declines again after being invited to a changed session, associate that decline with the new start, end, and venue.
- Apply this behavior to every decline, regardless of how the response was recorded. Recording that a reply arrived by iMIP, and using that provenance to determine calendar cancellation behavior, remains the separate scope of [US-15](US-15-inbound-calendar-response-parsing.md).
