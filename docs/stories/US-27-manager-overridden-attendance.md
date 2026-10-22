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
- When a manager changes someone's attendance on a booking that has not ended, that person receives an email confirming the attendance just saved for that booking. Nobody else on the roster is mailed. A member who changes their own attendance does not get this mail; [US-17](US-17-schedule-digest-changelog.md) sends the daily rota summary instead. A past booking stays silent.
