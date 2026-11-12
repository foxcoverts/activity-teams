---
id: US-44
title: "Tentative Booking Manager Nudges & Reminder Alerts"
status: not-started
area: safety
scope: extra
---

## Story

As an Activity Manager, I want to receive automated periodic reminders and digest notifications about upcoming tentative bookings so that I am prompted to review, staff, or cancel them even when I'm not actively looking at the system.

## Acceptance criteria

- Automatically dispatches scheduled reminder notifications or digest summaries to managers regarding unconfirmed tentative bookings.
- Highlights approaching tentative sessions that require action (such as confirming or cancelling) directly in notification channels or summary emails.
- A reminder about one tentative booking may set `References` to that booking's current revision. A digest of many bookings does not.

## Progress

Not started for mail. Shared readiness / attention queries live in `App\Support\ManagerAttention` (used by the [US-41](US-41-tentative-booking-triage.md) Manager dashboard). Digest delivery on the CombinedDigest job is still outstanding. [US-61](US-61-notification-settings.md) keeps this always-on: no preference toggle and no preferences footer.
