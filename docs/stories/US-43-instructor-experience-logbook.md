---
id: US-43
title: "Instructor Experience Logbook & Optional Personal Notes"
status: done
area: safety
scope: extra
---

## Story

As a team member, I want the system to automatically compile my session history into a personal experience logbook — with the optional ability to add custom notes about what I actually delivered — so that I can easily export my records for permit assessments.

## Acceptance criteria

- Automatically generates a personal session logbook for instructors based on confirmed bookings and completed debriefs.
- Allows instructors to optionally add personal notes or descriptions to specific log entries if they wish to keep a detailed personal record.
- Provides a clean export view for instructors to present their variety of experience and currency when applying for, upgrading, or renewing permits.

## Progress

- **Done (Phase C):** Accepted-only outstanding candidates (session day ≤ today; ignore via `logbook_ignored_at`; Tentative never listed). Member **Personal → Logbook** with outstanding + saved tables, badge, Add / Ignore / Decline (decline reuses attendance + iMIP when the booking still accepts invites). Remove from saved log deletes the entry and ignores the session so it stays off Outstanding (re-add from the booking still clears ignore). Empty historical backfill for existing Accepted pivots. Debrief prefill/copy for hours, participants, and weather only (debrief notes read-only). Saved-log filters (schedule, multi-activity, team) carry into CSV and `/logbook/export`. `bookings.logbook` mail channel defaults **off**; focused nudge + combined-digest summary via `LogbookCatchUp`.
