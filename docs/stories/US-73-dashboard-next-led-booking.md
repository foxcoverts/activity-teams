---
id: US-73
title: "Dashboard: Next Booking I Lead"
status: done
area: bookings
scope: extra
---

## Story

As a lead instructor, I want my next day of led bookings to stay visible on my dashboard, so that I can prepare for it even when it is more than a week away.

## Acceptance criteria

- Finds the day of the member's next confirmed booking as lead instructor, and shows every confirmed booking the member leads on that day. The day is decided by each booking's own timezone. Tentative and cancelled bookings are not shown.
- If that day falls inside the [US-70](US-70-dashboard-this-week.md) window, those bookings are shown there and this section is hidden. This section never repeats a booking.
- Each row shows the date and time, activity and audience, venue name, team, and a link to the booking view where the session roster ([US-52](US-52-lead-instructor-session-roster.md)) lives.
- A row warns when the member leads the booking but has not accepted it, using the same lead attendance warning as the booking views. Under the current lead rules a lead instructor must have accepted the booking, and changing their attendance clears them as lead, so this state should not occur. The row is kept as a safeguard.
- Phone numbers and emergency contacts are not shown. Access to them stays under [US-18](US-18-lead-instructor-contact-access.md) and its time window.
- No key holder indicator and no roster readiness summary is shown. Readiness is a manager concern.
- The section is hidden when the member leads nothing confirmed in the future, per [US-69](US-69-member-dashboard.md).

## Open questions

- None. A led day is either fully inside the US-70 window (shown there, and this section is hidden) or fully outside it, because both use the booking's timezone. This was confirmed.
