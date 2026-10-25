---
id: US-28
title: "Booking Cancellation Reasons & Participant Notification"
status: partial
area: bookings
scope: required
---

## Story

As an Activity Manager, I want to provide a mandatory reason when cancelling a booking so that participants are accurately informed of why the session is called off.

## Acceptance criteria

- Cancelling a booking requires the manager to input a mandatory cancellation reason string.
- The cancellation reason is logged in the booking history and included in automated cancellation notification emails sent to invited or confirmed team members. Someone who declined is included only when [US-15](US-15-inbound-calendar-response-parsing.md) has recorded that their decline arrived as an iMIP `REPLY`.
- The reason stays in the body. The subject is `Cancelled: {Activity} at {Venue}, {start}` and names the local start, not the finish time or the group.

## Progress

Cancelling requires a reason. It is stored as the booking activity-log description and included in cancellation mail. The subject is `Cancelled: {Activity} at {Venue}, {start}`. Recipients are invited or confirmed attendees; everyone who declined is skipped. Sending cancellation to an iMIP `REPLY` decline waits on [US-15](US-15-inbound-calendar-response-parsing.md).
