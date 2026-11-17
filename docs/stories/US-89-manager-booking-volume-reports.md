---
id: US-89
title: "Manager Booking Volume Reports"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want a Teams **Reports** page that summarises booking volume for a calendar year—with year-on-year comparison, breakdowns by activity, audience, and venue, and hygiene for cancelled and past-still-tentative sessions—so that I can see what the team actually ran, using debrief figures when they exist.

## Acceptance criteria

- A manager-only Teams page **Reports** (not the Manager triage dashboard) is available when the viewer `manages($team)`.
- The manager picks a calendar year; comparison baseline is always the previous calendar year (no separate compare control).
- Summary shows confirmed count, cancelled count, cancellation rate (`cancelled ÷ (confirmed + cancelled)`), and **Hours** for confirmed bookings, each with this year / last year / change. Hours use debrief operating hours when a debrief exists, otherwise confirmed session length.
- When the selected year has at least one debrief, summary also shows **Participants** (sum of debrief participant numbers) with YoY compare. When there are no debriefs in that year, the Participants metric is hidden (not a wall of zeroes).
- Overview includes a quiet coverage note (“:debriefed of :confirmed sessions debriefed”) when there is at least one confirmed booking in the year.
- Past still tentative (`status = tentative` and past) appears as a hygiene snapshot with a short list and a prior-year comparator caveat (live leftovers stock, not seasonal volume).
- Breakdown tables for activity, audience, and venue show confirmed counts for Y / Y−1 / Δ; cancelled counts are visible where useful. The audience table adds participant columns only when the selected year has debriefs.
- A compact multi-year trend (up to five calendar years) shows confirmed and cancelled totals.
- Future tentatives stay on Manager triage; they are not mixed into Reports volume.
- Deep links into Bookings history use status and a schedule date filter for the selected calendar year; that filter matches bookings whose timeframe overlaps the range (not start-only).

## Progress

Done on Teams **Reports** via `ManagerBookingReports` aggregates and the Filament Reports page. Member engagement is [US-90](US-90-manager-member-engagement-reports.md). Hours prefer debrief values when present; participants and audience participant columns appear once the year has debrief coverage. Year volume uses the same overlapping timeframe as the Bookings schedule filter.
