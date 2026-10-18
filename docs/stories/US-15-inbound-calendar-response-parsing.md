---
id: US-15
title: "Inbound Calendar Response & Webhook Parsing"
status: not-started
area: communications
---

## Story

As an Activity Manager, I want the system to reliably parse incoming calendar email responses (including complex verbs, unknown users, and signature comments) so that attendance records update automatically.

## Acceptance criteria

- Gracefully handles replies from unmapped email addresses.
- Parses advanced calendar verbs like COUNTER and REFRESH.
- Strips extraneous mail-server signature COMMENT blocks.
- Identifies the booking from the calendar UID, `booking+{id}@{host}`. That UID does not include the sequence. The email `References` value, `booking-{id}-sequence-{n}@{host}`, is a different identifier and does not select the booking.
- Records that an attendance response arrived as an iMIP `REPLY`, separately from a response made in the app.
- A later cancellation uses that record. Someone who declined is sent the cancellation only when their decline was an iMIP `REPLY`, so their calendar can apply `METHOD:CANCEL`. A decline made in the app is not sent a cancellation.
