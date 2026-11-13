---
id: US-44
title: "Tentative Booking Manager Nudges & Reminder Alerts"
status: done
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

Done on the CombinedDigest job. `ManagerAttentionCatchUp` classifies each managed team's [US-41](US-41-tentative-booking-triage.md) buckets (Needs lead, Ready to confirm, Unstaffed, At risk) into **new** (never stamped, or triage changed) vs **outstanding** (same triage as last stamp). Per-manager stamps live in `manager_attention_notifications`. Mail sends when there is at least one new item: a single new item uses focused `ManagerAttentionNudgeMail` with booking `References`; otherwise CombinedDigest sections **Needs your attention** then **Still outstanding**. Outstanding-only days do not send attention mail. Always-on ([US-61](US-61-notification-settings.md)): no preference toggle and no preferences footer. CTA opens the Teams Manager dashboard. Needs-lead **email** is via this attention path only; the LeadNeeded inbox row stays for in-app and is not replayed as digest mail.
