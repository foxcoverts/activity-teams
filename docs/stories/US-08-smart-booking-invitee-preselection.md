---
id: US-08
title: "Smart Booking Invitee Pre-Selection & Management"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want the system to automatically evaluate activity rules and qualifications upon booking creation so that appropriate team members are pre-selected on a targeted invite screen rather than managing a bulk select-all list.

## Acceptance criteria

- When a new booking is initialised, the system runs filtering logic based on activity type, venue, and required permits.
- Presents the manager with an initial invite screen where relevant qualified members are pre-targeted.
- Replaces generic select-all or none checkbox grids with a streamlined management interface allowing smooth add and remove actions.

## Progress

The invite modal starts with people who meet the activity when it has qualification rows, and add and remove replace the checkbox list. An activity with no rows starts with an empty list. A member is not assigned to a venue, so the invite list is not filtered by venue. The booking already has its venue, and that choice selects the keys. Key holders only narrows the team list to people who hold one of those keys, without changing who starts in the queue. The Qualified badge opens that person's in-date qualifications for this activity, and from there their other qualifications and a permit's restrictions. A lead seeing the same details on their own booking is [US-52](US-52-lead-instructor-session-roster.md).
