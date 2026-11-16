---
id: US-91
title: "Admin Dashboard Triage"
status: done
area: administration
scope: extra
---

## Story

As a system admin (or oversight user), I want the Admin dashboard to surface site-wide problems that need attention—inbound mail to review, stuck site-access invitations, and teams without managers—so that I do not rely only on digests and hunting through resource lists.

## Acceptance criteria

- The Admin dashboard (`/admin`) shows triage sections in a single column, hide-when-empty, with preview rows and “See all” deep links (Manager dashboard pattern).
- **System admins** see: inbound mail needing review, pending site-access invitations, teams without managers, and separate catalog sections for activities/venues with no team, audiences with no activity, and keys with no locks, plus a thin snapshot (team, activity, venue, audience, and key counts). Non-empty triage sections are packed into two stacked columns from the `md` breakpoint (each section stays whole; a single occupied column spans full width).
- **Oversight** see directory-style items only (pending site-access invitations, teams without managers, snapshot)—not inbound mail or catalog triage (those remain system-admin only).
- Dashboard nav badge reflects the total of buckets the viewer can see; severity is destructive when inbound failures or stale review items exist, otherwise uncertain when any queue is non-empty.
- Empty queues show an “all caught up” state for the viewer’s visible buckets.
- Does not add booking volume analytics or site-wide bounce rollups (those stay on Teams Manager / Reports).

## Progress

Done via `AdminAttention` and Admin dashboard widgets. Lifts the Admin dashboard deferral noted on [US-69](US-69-member-dashboard.md) for this triage slice only.
