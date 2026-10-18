---
id: US-09
title: "Mandatory Lead Instructor Confirmation Validation"
status: done
area: bookings
---

## Story

As an Activity Manager, I want the system to prevent a booking from being marked as Confirmed unless a valid lead instructor is assigned so that activity governance policies and safety requirements are consistently met.

## Acceptance criteria

- The system validates booking state transitions; moving a booking from Tentative to Confirmed requires a valid lead instructor to be assigned.
- Displays a clear validation error or warning if a manager attempts to confirm a session without a designated lead instructor holding the correct qualification for that activity type.

## Progress

Confirm stays closed until someone has accepted, and saving a lead who has not accepted is refused. When the activity lists qualifications, the lead must meet them as well. An activity with no rows accepts any attending lead.
