---
id: US-09
title: "Mandatory Lead Instructor Confirmation Validation"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want the system to prevent a booking from being marked as Confirmed unless a valid lead instructor is assigned so that activity governance policies and safety requirements are consistently met.

## Acceptance criteria

- The system validates booking state transitions; moving a booking from Tentative to Confirmed requires a valid lead instructor to be assigned **when the booking requires a lead instructor** ([US-78](US-78-optional-lead-instructor.md)).
- Displays a clear validation error or warning if a manager attempts to confirm a session that requires a lead without a designated lead instructor holding the correct qualification for that activity type.

## Progress

Confirm stays closed until someone has accepted, and saving a lead who has not accepted is refused, when the booking requires a lead. When the activity–audience pairing lists qualifications, the lead must meet them as well. A pairing with no rows accepts any attending lead. Saving a booking whose lead no longer meets the pairing is refused. See [US-66](US-66-audience-qualification-requirements.md). Bookings that turn off the lead requirement confirm without a lead ([US-78](US-78-optional-lead-instructor.md)).
