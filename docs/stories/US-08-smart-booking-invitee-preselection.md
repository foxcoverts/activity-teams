---
id: US-08
title: "Smart Booking Invitee Pre-Selection & Management"
status: partial
area: bookings
---

## Story

As an Activity Manager, I want the system to automatically evaluate activity rules and qualifications upon booking creation so that appropriate team members are pre-selected on a targeted invite screen rather than managing a bulk select-all list.

## Acceptance criteria

- When a new booking is initialised, the system runs filtering logic based on activity type, venue, and required permits.
- Presents the manager with an initial invite screen where relevant qualified members are pre-targeted.
- Replaces generic select-all or none checkbox grids with a streamlined management interface allowing smooth add and remove actions.

## Progress

The invite modal starts with people who meet the activity when it has qualification rows, and add and remove replace the checkbox list. An activity with no rows starts with an empty list. Venue and key filtering are still open, with [US-03](US-03-booking-venue-access-logistics.md) and [US-04](US-04-multi-venue-location-management.md).
