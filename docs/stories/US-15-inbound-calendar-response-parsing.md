---
id: US-15
title: "Inbound Calendar Response & Webhook Parsing"
status: not-started
area: communications
scope: required
---

## Story

As an Activity Manager, I want the system to reliably parse incoming calendar email responses (including complex verbs and signature comments) so that attendance records update automatically.

## Acceptance criteria

- Applies an inbound iMIP `REPLY` only when the attendee maps to a known person. Unmapped addresses are retained by [US-55](US-55-inbound-email-routing-and-alerts.md); identity linking is [US-62](US-62-unmapped-calendar-reply-identity.md).
- Parses advanced calendar verbs like COUNTER and REFRESH.
- Strips extraneous mail-server signature COMMENT blocks.
- Identifies the booking from the calendar UID, `booking+{id}@{host}`. That UID does not include the sequence. The email `References` value, `booking-{id}-sequence-{n}@{host}`, is a different identifier and does not select the booking.
- Records that an attendance response arrived as an iMIP `REPLY`, separately from a response made in the app.
- A later cancellation uses that record. Someone who declined is sent the cancellation only when their decline was an iMIP `REPLY`, so their calendar can apply `METHOD:CANCEL`. A decline made in the app is not sent a cancellation.

## Progress

A manager attendance edit stores `via: manager` on the booking activity entry, with the manager as the causer. That is a third channel, separate from the member's own reply and from an iMIP `REPLY`. Parsing inbound calendar mail, and sending a cancellation only for an iMIP decline, is still open.

The legacy mail log was used to check inbound iMIP processing during operation and development. A general mail-log interface is not required for the rewrite; retaining and routing other inbound replies and surfacing processing failures are tracked separately in [US-55](US-55-inbound-email-routing-and-alerts.md).
