---
id: US-72
title: "Dashboard: Responses Still Needed"
status: done
area: bookings
scope: extra
---

## Story

As a team member, I want my dashboard to show the invitations I have not answered and the bookings I answered Maybe to, so that I can confirm whether I can attend before the organisers have to chase me.

## Acceptance criteria

- Lists non-cancelled bookings starting after the [US-70](US-70-dashboard-this-week.md) window where the member's status is Awaiting response or Maybe. Bookings inside that window are shown by US-70 and are not repeated.
- The two states are shown separately: **Awaiting response** and **Maybe**. They are not merged into one "outstanding" list.
- Awaiting response has no distance limit. Maybe is shown only up to three months ahead. The three-month limit applies only to this dashboard section.
- Within each group bookings are ordered by start time, soonest first.
- Each row shows the date and time, activity and audience, venue name, team, and a response action that opens the same modal as [US-70](US-70-dashboard-this-week.md).
- A Maybe row encourages the member to confirm, since they have already answered but not usefully. An Awaiting response row asks for a first answer.
- A response updates the row immediately. A row leaves this section when the member answers Can help or Cannot make it.
- Each group shows five rows, with a link to the matching list on the member bookings area. When more than five Awaiting response bookings exist, the group shows the total outstanding count.
- What counts as outstanding (Awaiting response or Maybe on a non-cancelled booking) is one shared definition, used by this section, the Invites tab on the member bookings list, and later by [US-16](US-16-invitation-nudges-reminders.md), so the dashboard, the list, and email reminders always agree. The Invites tab has no three-month limit on Maybe.
- Tentative bookings and bookings with no lead are not given extra emphasis or earlier ordering here. A readiness measure is out of scope; if one arrives later (see [US-41](US-41-tentative-booking-triage.md)) it can be added then.
- Each group is hidden when empty, and the whole section is hidden when both groups are empty, per [US-69](US-69-member-dashboard.md).

## Notes

- The three-month Maybe limit is confirmed.