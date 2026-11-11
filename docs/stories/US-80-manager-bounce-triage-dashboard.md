---
id: US-80
title: "Manager Bounce / Contact-Failure Triage"
status: not-started
area: communications
scope: extra
---

## Story

As an Activity Manager, I want a Teams dashboard surface that lists members whose email addresses have permanently bounced so that I can triage contact failures in one place and update details before they miss more invitations.

## Acceptance criteria

- The Teams (or manage) dashboard shows members with a permanent email bounce for teams the viewer manages.
- Each row deep-links to that member's profile where the bounced address is visible.
- Optional filters or counts help prioritise remediation.
- Builds on [US-45](US-45-email-delivery-bounce-tracking.md) flags and manager notifications; does not reopen Message-ID or webhook work.

## Progress

Not started. Same family as [US-41](US-41-tentative-booking-triage.md) and the deferred Teams manager attention note on [US-69](US-69-member-dashboard.md). [US-45](US-45-email-delivery-bounce-tracking.md) covers flagging, profile visibility, and the manager notice with a profile link.
