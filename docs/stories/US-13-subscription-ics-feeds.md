---
id: US-13
title: "Subscription ICS Export Feeds"
status: done
area: communications
scope: required
---

## Story

As a Team Member, I want a secure, token-based .ics calendar feed link so I can subscribe to my personalised schedule in external calendar applications.

## Acceptance criteria

- Generates unique secure tokens per user for calendar feeds.
- A member can revoke or regenerate their calendar-feed token; a revoked token immediately stops granting access to the feed.
- Exports comprehensive event metadata including structured locations, booking descriptions, and correct privacy classifications (CLASS:PRIVATE).
- Incorporates RELATED-TO fields into exported VEVENT components where bookings are linked as a multi-day camp or series, using RELTYPE=SIBLING because linked sessions are peers.
- The event UID stays `booking+{id}@{host}` when the booking sequence changes. That UID is not the email `References` value, which includes the sequence.

## Progress

Feeds, per-user tokens (including revoke/regenerate), CLASS:PRIVATE, and sibling RELATED-TO are in place. Precise LOCATION and GEO are [US-65](US-65-ics-precise-location.md) and are done; do not treat that follow-up as reopening this story. [US-22](US-22-task-management.md) adds `tasks.ics` and `everything.ics` on the same `feed_token` without reopening this story. When assignees ship, [US-79](US-79-task-assignees-and-imip.md) should extend `rota.ics` with assigned-task VTODOs.
