---
id: US-18
title: "Time-Bound Event Contact Access (Lead Instructors)"
status: done
area: safety
scope: required
---

## Story

As a Lead Instructor, I want time-bound access to participant contact and emergency details relative to my scheduled booking window so I can manage coordination and handle emergencies safely.

## Acceptance criteria

- A lead instructor can see the phone number of each person who is attending (accepted or tentative) on their booking, until 23:59:59 on the booking end date. There is no earlier start.
- A lead instructor can see the emergency contact name and phone of those same people from 00:00 on the booking start date through 23:59:59 on the booking end date.
- People who have declined or not responded are never shown to the lead.
- Nothing is shown for a cancelled booking, or when either person is suspended.
- Before viewing the details, the lead instructor must acknowledge their proper use as required by [US-58](US-58-personal-data-viewing-acknowledgement.md).

## Progress

Dates are those of the booking's own timezone, not the viewer's, so a booking that crosses midnight has one window for everyone. The booking timezone is required and not null in the database. A manager of the team can view phone and emergency details from the booking guest list without a time window (the same access they already have via Members). A system admin does not bypass this guard.

Shown from the guest list: a Contact action opens a modal with separate US-58 acknowledgements for phone and emergency. Empty phone numbers are omitted; emergency without a phone number is treated as missing and points attendees to a team manager. Manager rows keep Edit and Remove in a kebab menu. Rules live in `BookingAttendancePolicy::viewPhone` and `viewEmergencyContact`.
