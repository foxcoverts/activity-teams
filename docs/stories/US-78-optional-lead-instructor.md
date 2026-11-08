---
id: US-78
title: "Optional Lead Instructor on Booking"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want to turn off the lead-instructor requirement on a booking, so the team can track attendance at an externally hosted event without inventing a team lead or blocking confirmation.

## Acceptance criteria

- The booking form has a collapsed **Settings** section at the bottom with **Require lead instructor**, default on. It is not a header control or a separate create route.
- Helper copy explains turning it off for events led outside the team, and that organiser details can go in notes. There is no separate organiser field.
- When the setting is on, confirmation still requires an accepted, qualified team lead ([US-09](US-09-mandatory-lead-instructor-confirmation.md)).
- When the setting is off: Confirm has no lead picker; `lead_instructor_id` stays null; no accepted attendee is required to confirm. The Change Lead action is hidden.
- Turning the requirement off clears any assigned lead. Turning it back on while the booking is confirmed without a lead moves the booking to tentative.
- Lead decline / clear-lead flows that would force tentative only apply when the booking requires a lead.
- The guest list remains the place that shows who is leading; the booking details block does not repeat the lead. Mail, calendars, and dashboards that assume a team lead ignore or omit lead/CHAIR when none is required.
- **Other** does not imply “no lead”; managers must use Settings. Inviting non-members stays [US-50](US-50-invite-non-members.md).

## Progress

Done. Bookings have **Require lead instructor** (default on) in a collapsed Settings section. Confirm and Change Lead follow that flag; turning it off clears the lead and allows confirm without attendees; turning it back on while confirmed without a lead returns the booking to tentative. Organiser details stay in notes. The guest list remains the lead surface.
