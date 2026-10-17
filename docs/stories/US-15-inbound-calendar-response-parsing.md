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
