---
id: US-10
title: "Lead Instructor Availability Change & Resignation Workflow"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want the system to automatically handle when a lead instructor changes their availability by reverting the booking to Tentative and alerting me so that shifts are never left unverified.

## Acceptance criteria

- Intercepts declined attendance responses from leads.
- Automatically reverts booking state to Tentative, unassigns the instructor, and triggers manager alerts.
- An alert about one booking may set `References` to that booking's current revision. The subject can stay distinct from the invite and update subjects.

## Progress

A lead who answers maybe or no is unassigned. A confirmed upcoming booking returns to tentative. A comment alone changes neither. A manager who records maybe or no for that lead on an upcoming booking uses the same rule. On a past booking the lead is cleared and the booking status stays as it is. The same unassign/revert runs when the lead is suspended or a manager removes the lead.

When attendees are already sent an update (for example a manager removes the lead), that update is kept for everyone on the booking, including the person who made the change, so calendar/iMIP updates still go out. Managers on the booking get a “lead instructor needed” panel on that update instead of a second mail. A separate `Lead needed:` mail goes only to other current, active, verified managers who are not getting that update. The person who caused the change is not sent the extra mail. Unverified, inactive, and suspended managers are skipped. Inbound calendar decline still waits on [US-15](US-15-inbound-calendar-response-parsing.md).
