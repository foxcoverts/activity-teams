---
id: US-92
title: "External Logbook Sessions & Delete-Safe Snapshots"
status: done
area: safety
scope: extra
---

## Story

As a team member, I want to add sessions that are not tied to a team booking to my personal experience logbook — and keep booking-linked entries when a cancelled booking is deleted — so that my permit-facing record stays complete.

## Acceptance criteria

- Members can add an external/additional session to their personal logbook with date (required), optional start time, activity, venue, optional role, and the usual hours / participants / weather / notes.
- Activity, venue, and role are free text with suggested values (catalog activity names, venue names from the member’s teams, and a short role shortlist: Supervisor, Instructor, Assistant, Coaching Session, Observer, Personal Climbing).
- Booking-linked entries keep reading schedule / activity / venue live from the booking while it exists; an optional role is stored on the entry.
- When a booking is deleted, each related logbook entry keeps its personal fields and receives a snapshot of date, time, timezone, activity title, and venue name; `booking_id` is cleared instead of cascading the entry away.
- Saved log, filters, and CSV/PDF export include external and orphaned entries (activity filter by resolved title; team filter remains booking-only).

## Progress

- **Done:** Nullable `booking_id` with `nullOnDelete`; snapshot columns (`occurred_on`, `start_time`, `timezone`, `activity_label`, `venue_name`, `role`); `Logbook::addExternal` / snapshot on booking delete; member **Add session** with datalists; Role on booking-linked forms; filters/export updated. Covered in `tests/Feature/LogbookTest.php`.
