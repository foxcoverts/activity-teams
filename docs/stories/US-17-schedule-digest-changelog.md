---
id: US-17
title: "Scheduled Schedule Digest & Changelog Notifications"
status: not-started
area: communications
---

## Story

As a team member, I want to receive an automated periodic digest email summarising schedule changes — featuring a changelog of recent updates followed by my full upcoming roster — so I can easily track shifts without being spammed by instant alerts.

## Acceptance criteria

- Dispatches automated schedule digests on a scheduled periodic frequency (for example, daily) rather than firing real-time notifications for every individual tweak.
- Highlights recent modifications (such as additions, time shifts, or cancellations) at the top of the email.
- Lists upcoming commitments below the changelog, fully respecting individual user notification preference settings.
- The digest covers many bookings, so it does not set `References` to one booking's revision id.
