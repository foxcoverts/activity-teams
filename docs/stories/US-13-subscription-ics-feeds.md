---
id: US-13
title: "Subscription ICS Export Feeds"
status: partial
area: communications
scope: required
---

## Story

As a Team Member, I want a secure, token-based .ics calendar feed link so I can subscribe to my personalised schedule in external calendar applications.

## Acceptance criteria

- Generates unique secure tokens per user for calendar feeds.
- Exports comprehensive event metadata including structured locations, booking descriptions, and correct privacy classifications (CLASS:PRIVATE).
- Incorporates RELATED-TO fields into exported VEVENT components where bookings are linked as part of a multi-day camp or series, allowing external calendar apps to recognise parent and child event groupings.
- The event UID stays `booking+{id}@{host}` when the booking sequence changes. That UID is not the email `References` value, which includes the sequence.
