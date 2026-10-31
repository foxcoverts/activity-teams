---
id: US-66
title: "Audience-Specific Qualification Requirements"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want each activity to offer audiences with their own qualification requirements so that one activity can share its keys while each audience has the right lead rule.

## Acceptance criteria

- A system admin keeps a shared audience catalogue and attaches each audience to an activity. Qualification rows belong to that pairing. A pairing with no rows accepts any lead. An activity and audience with no pairing cannot be booked.
- A booking stores one audience. Unqualified people may still be invited, added, and may accept. When the pairing has rows, people who meet them are obvious on the manage guest list, and only one of those people who has also accepted can be the lead.
- Keys stay on the activity and venue. The audience does not change which keys a booking needs.
- Saving a booking is refused when an assigned lead no longer meets the pairing. Changing the audience is sent to people already on the booking, and it appears on signed-in booking screens, calendar invites, and booking mail. The logged-out summary stays activity, date, time, and venue.
- A team can set or clear an optional default audience on each activity it offers. A new booking uses that default when the audience is still offered. Changing activity keeps the selected audience when the new activity offers it, and otherwise uses the new activity's default or leaves audience empty.
- Group is optional on every booking. A blank group is omitted from the summary, calendar title, and mail. List titles fall back to the activity and audience.

## Progress

Audiences are a site-admin catalogue. On an activity, each attached audience has the acceptable-qualification repeater from [US-02](US-02-activity-specific-qualification-staffing.md): each row is one way to qualify, a blank field means any value, and no rows means anyone may lead. Rows for one pairing do not qualify a different audience of the same activity. A booking must name an audience that activity offers. Mixed audiences on one booking stay out of scope.

The manage guest list, invite picker, confirm, and change-lead use that pairing, as of the booking end date. Keys are unchanged. A team sets the optional default audience on its activity list, stored on the team–activity row. The logged-out page is still [US-12](US-12-public-booking-summary.md).
