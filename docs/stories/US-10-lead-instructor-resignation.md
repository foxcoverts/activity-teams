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

A lead who answers maybe or no is unassigned, including from the booking page after they have already accepted. A confirmed upcoming booking returns to tentative. A comment alone changes neither. The accepted lead still cannot be removed from the roster; they step down by changing their answer.

The respond UI is still too quiet about that. Helper text says “You are the lead instructor,” and the extra sentence about finding another lead only appears on confirmed bookings. A later pass should make Maybe/No obviously a step-down on both the booking page and the invite respond screen, including while the booking is still tentative. A manager who records maybe or no for that lead on an upcoming booking uses the same rule. On a past booking the lead is cleared and the booking status stays as it is. The same unassign/revert runs when the lead is suspended or a manager removes the lead.

When attendees are already sent an update (for example a manager removes the lead), that update is kept for everyone on the booking, including the person who made the change, so calendar/iMIP updates still go out. Managers on the booking get a “lead instructor needed” panel on that update instead of a second mail. Other current, active, verified managers who are not getting that update get a lead-needed inbox item ([US-67](US-67-member-notification-inbox.md)) for in-app. Digest **email** for Needs lead is always-on via Manager attention ([US-44](US-44-tentative-booking-manager-nudges.md)), not a replay of that inbox row and not gated by `bookings.updates`. The person who caused the change is not notified. Unverified, inactive, and suspended managers are skipped. An inbound iMIP decline uses the same attendance action, so a lead who declines by calendar is unassigned the same way ([US-15](US-15-inbound-calendar-response-parsing.md)).
