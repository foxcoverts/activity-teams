---
id: US-89
title: "Manager Booking Volume Reports"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want a Teams **Reports** page that summarises booking volume for a calendar year—with year-on-year comparison, breakdowns by activity, audience, and venue, and hygiene for cancelled and past-still-tentative sessions—so that I can see what the team actually ran without waiting for rich debrief data.

## Acceptance criteria

- A manager-only Teams page **Reports** (not the Manager triage dashboard) is available when the viewer `manages($team)`.
- The manager picks a calendar year; comparison baseline is always the previous calendar year (no separate compare control).
- Summary shows confirmed count, cancelled count, cancellation rate (`cancelled ÷ (confirmed + cancelled)`), and scheduled hours for confirmed bookings, each with this year / last year / change.
- Past still tentative (`status = tentative` and past) appears as a hygiene snapshot with a short list and a prior-year comparator caveat (live leftovers stock, not seasonal volume).
- Breakdown tables for activity, audience, and venue show confirmed counts for Y / Y−1 / Δ; cancelled counts are visible where useful.
- A compact multi-year trend (up to five calendar years) shows confirmed and cancelled totals.
- Future tentatives stay on Manager triage; they are not mixed into Reports volume.
- Delivered debrief hours / participant counts are out of scope (see follow-up when debrief coverage is useful).

## Progress

Done on Teams **Reports** via `ManagerBookingReports` aggregates and the Filament Reports page. Member engagement is [US-90](US-90-manager-member-engagement-reports.md). Debrief enrichment remains a later story.
