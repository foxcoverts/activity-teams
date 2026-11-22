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

Done on the CombinedDigest job. `ManagerAttentionCatchUp` classifies each managed team's [US-41](US-41-tentative-booking-triage.md) buckets (Needs lead, Ready to confirm, Unstaffed, At risk) for mail only when start is within the widest `ScheduleNudgeTier` window (`ScheduleNudgeTier::first()`, currently **56 days**), using the same ladder and half-life as [US-16](US-16-invitation-nudges-reminders.md) (56 / 28 / 14 / 7). Per-manager stamps in `manager_attention_notifications` store triage + `nudge_tier`. **New** means a due unmet tier (or triage change that re-enters the ladder); **outstanding** is same triage with no new tier. Mail sends when there is at least one new item: a single new item (within a team-focused send) uses `ManagerAttentionNudgeMail` with booking `References`; otherwise the **per-team** CombinedDigest sections **Needs your attention** then **Still outstanding**. Outstanding-only days do not send attention mail. The Manager dashboard still lists all upcoming tentatives; the Manager nav badge shares this first-tier notification-window horizon for booking triage counts (debriefs and bounces always count). Always-on ([US-61](US-61-notification-settings.md)): no preference toggle and no preferences footer. CTA opens that team's Manager dashboard. Needs-lead **email** is via this attention path only; the LeadNeeded inbox row stays for in-app and is not replayed as digest mail.
