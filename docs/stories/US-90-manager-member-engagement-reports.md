---
id: US-90
title: "Manager Member Engagement Reports"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want to see which current team members rarely respond to booking invitations or rarely help on sessions, so that I can follow up on silence and capacity without relying on debriefs.

## Acceptance criteria

- On the Teams **Reports** page ([US-89](US-89-manager-booking-volume-reports.md)), a member engagement table lists **current** team members for the selected calendar year.
- Metrics use invitations on **non-cancelled** bookings whose start falls in that year: invites, response rate (answered ÷ invited), can-help rate (`accepted` ÷ invited), optional roll-call attended rate when the booking has any `attendance_confirmed_at`, and lead load (`lead_instructor_id`).
- **Never responded** means invited at least once and every roster row is still `needs-action`.
- Default sort favours most can-help (accepted), then other responses, then everyone else (by invites, then name). Section copy states that ordering.
- Team-wide response and can-help rates appear on the summary strip with year-on-year comparison; per-member YoY is out of scope.
- Rows link to the member view (PII remains gated by [US-58](US-58-personal-data-viewing-acknowledgement.md)).
- UI copy notes that attendance is verified only where roll-call exists.

## Progress

Done on the same Teams **Reports** page as [US-89](US-89-manager-booking-volume-reports.md), using `ManagerBookingReports` member engagement aggregates.
