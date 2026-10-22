---
id: US-03
title: "Booking Venue Access & Logistics Planning"
status: not-started
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want each booking to evaluate and display on-the-day venue access requirements (verifying whether an attending team member is a keyholder, whether a warden check is required, or if a separate key transfer must be arranged) so that activities never get locked out on arrival.

## Acceptance criteria

- Automatically checks the attendance roster to detect if at least one confirmed attendee holds the required venue or equipment key.
- Displays clear access status indicators on the booking view (for example, Access Secured via Attendee Key, Warden on Site Required, or Key Transfer Needed).
- Provides logistical action triggers to coordinate key issuance to non-keyholder lead instructors prior to the event window.

## Progress

The roster check looks for a holder of the key that booking needs. A warden holds the warden key, which covers every campsite key, as though that one key opened those locks. The keys in the safe are not tracked. That is still not a separate bypass. "Warden on site required" is the climbing-only shorthand for nobody attending holding the access the booking needs. Not built yet.
