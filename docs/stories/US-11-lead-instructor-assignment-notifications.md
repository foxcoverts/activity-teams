---
id: US-11
title: "Lead Instructor Assignment Notifications"
status: done
area: bookings
scope: required
---

## Story

As a team member, I want to be told when I am made the lead instructor of a booking so that I know which session I am responsible for.

## Acceptance criteria

- The person who is set as a booking's lead instructor is told about that booking.
- The subject can stay distinct from the invite and update subjects.
- The mail may set `References` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`.

## Progress

The lead is told in their existing confirm or update mail, not a separate message. The heading stays `Booking confirmed` / `Booking updated`. When they are confirmed as lead, or when `lead_instructor_id` changes to them, the highlighted panel says they have been given that responsibility instead of listing the lead as a field change. Other attendees keep the generic confirm/update copy. Subjects stay `Confirmed:` / `Updated:`; `References` already match the story. Lead-instructor notes are included on confirm/update mail to the current lead when present, including later updates that do not repeat the responsibility notice.
