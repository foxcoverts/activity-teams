---
id: US-26
title: "Filterable Roster Additions & Batch Management"
status: done
area: bookings
---

## Story

As an Activity Manager, I want to use quick filters when managing booking rosters so that I can easily search and pull additional team members onto the invitation list as staffing needs evolve.

## Acceptance criteria

- The invite picker can be narrowed by name, and to people who meet the activity.
- The manager can add everyone those filters currently show who is not already on the booking.

## Progress

The invite modal queues a list, then sends it. The picker can be narrowed by name, and to people who meet the activity when it has qualification rows. Add everyone shown skips anyone already on the booking, and anyone hidden by the current filters. Not asking someone again is that picker rule, not a separate filter. Role is not a filter. A key holder filter is [US-08](US-08-smart-booking-invitee-preselection.md), not this story.
