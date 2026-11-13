---
id: US-41
title: "Intelligent Tentative Booking Triage & Readiness Dashboard"
status: done
area: safety
scope: extra
---

## Story

As an Activity Manager, I want tentative bookings to be actively triaged and highlighted based on their staffing readiness (such as spotting when enough qualified attendees are available to confirm, or flagging unstaffed sessions approaching their date) so that sessions don't slip through the cracks.

## Acceptance criteria

- Highlights tentative bookings that are Ready to Confirm (having sufficient confirmed attendees with the required permits or qualifications).
- Flags upcoming At-Risk or Unstaffed tentative sessions with low or zero confirmed attendance as they approach their date.

## Progress

Done on the Teams **Manager** dashboard (`ManagerDashboard`), manager-only. Shared readiness lives in `App\Support\ManagerAttention` / `TentativeTriage`:

- **Needs lead** — `bookings.lead_needed_at` set when a confirmed booking loses its lead and steps down to tentative; listed even if the roster would otherwise be Ready.
- **Ready to confirm** — confirm is possible with current lead rules (lead-viable useful attendees, or any useful attendee when lead is not required). Counts on the Manager nav badge with positive (green) severity; amber/red still outrank it.
- **Unstaffed / At risk** — zero useful attendees; At risk when start is within 14 days.

Scheduled manager mail is delivered by [US-44](US-44-tentative-booking-manager-nudges.md) on the CombinedDigest job. Bounce triage is [US-80](US-80-manager-bounce-triage-dashboard.md) on the same page.
