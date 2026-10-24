---
id: US-61
title: "Booking Notification Settings"
status: not-started
area: communications
scope: required
---

## Story

As a team member, I want to choose which booking emails I receive globally or for a specific booking, so that I can control booking-related email.

## Acceptance criteria

- A member can configure global defaults for `comment_mail`, `invite_mail`, `change_mail`, `confirm_mail`, and `cancel_mail`.
- A member can override each of those settings for an individual booking. An unset booking-level value inherits the global setting, and an unset global value uses the system default.
- `comment_mail` supports `all`, `reply`, `leader`, and `none`; the other settings can be enabled or disabled.
- Notification preference records are private to the member and cannot be changed by another member.
- These preferences apply to booking-related emails. Whether they also control team news distribution is a separate decision for [US-54](US-54-team-news-posts.md).

## Refinement

Moved from [US-56](US-56-member-profile.md). Implement alongside the booking notification flows. Confirm precedence for mandatory operational notices. Not built yet.
