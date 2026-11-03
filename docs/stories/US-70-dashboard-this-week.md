---
id: US-70
title: "Dashboard: My Bookings This Week"
status: done
area: bookings
scope: extra
---

## Story

As a team member, I want to see the bookings I am attending or leading in the next seven days on my dashboard, so that I know what I have committed to this week and what still needs a response.

## Acceptance criteria

- The window is rolling: from the start of today for seven days (today plus the next six days), in each booking's own timezone. A booking starting today, or already in progress, still shows. Bookings that ended earlier today follow the same window and ordering as any other, but their row is dimmed. They cannot be responded to.
- Lists non-cancelled bookings in the window where the member is leading and has not declined, or whose attendance is Can help, Maybe, or Awaiting response.
- Bookings are ordered by start time, soonest first.
- Each row shows the date and time, activity and audience, venue name, team, and the member's own status as the existing `BookingAttendeeStatus` label, colour, and icon.
- A booking the member leads carries a **Lead instructor** badge.
- Bookings on the same date share one tile, showing the date once with a divider between bookings. A tentative booking is clearly marked as tentative beside its title. Confirmed bookings carry no extra status text.
- Awaiting response and Maybe are non-negative states that still need an answer this close to the date. They are shown prominently with a prompt to respond or confirm attendance, since it is not yet known whether the member can attend. Once the member answers Can help the booking stays here as a commitment; once they answer Cannot make it it disappears.
- The prompt for Maybe encourages the member to confirm, since they have answered but not in a useful way. It is worded differently from the prompt for Awaiting response.
- Responding opens a modal that shows the full booking infolist and the response choices, so the member acts without leaving the dashboard. It uses the same response flow as the booking view and [US-14](US-14-email-rsvp-action-buttons.md), so history, mail, and calendar behavior stay identical. The modal also links to the booking page.
- Bookings the member has declined do not appear, including ones they lead.
- The section shows five rows. If more exist, a link opens the bookings area on the member's rota.
- No "changed" marker is shown. The app does not record what a member has seen or when a booking changed for them, and this story adds no tables or tracking.
- The section is hidden when empty, per [US-69](US-69-member-dashboard.md).
