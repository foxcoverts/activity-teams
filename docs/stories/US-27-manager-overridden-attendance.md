---
id: US-27
title: "Manager-Overridden Attendance Updates"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want to manually update or override a team member's attendance status (for example, recording a verbal Yes or Can't go after catching up in person) so that attendance rosters reflect real-world confirmations accurately without waiting for digital responses.

## Acceptance criteria

- Managers can directly change a member's response state (such as moving someone from Pending or Invited to Going or Can't go) directly from the booking guest list or roster management interface.
- The system logs manual manager updates in the booking activity history audit trail (recording that the status was updated by a manager on behalf of the member).
- When a manager changes someone's attendance on a booking that has not ended, that person gets an inbox item, and the digest emails the existing attendance mail when `bookings.mail` allows. Nobody else on the roster is notified. A member who changes their own attendance does not get this item; [US-17](US-17-schedule-digest-changelog.md) sends the rota summary instead. A past booking stays silent.

## Progress

The override still saves when `bookings.mail` is off, and the inbox item is still written. The digest sends the attendance mail only when that channel allows. Self-service attendance stays silent; [US-17](US-17-schedule-digest-changelog.md) is the rota notice.
