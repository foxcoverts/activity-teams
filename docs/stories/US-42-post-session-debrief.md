---
id: US-42
title: "Post-Session Debrief, Timing Nudges & Attendance Sign-Off"
status: partial
area: safety
scope: extra
---

## Story

As a Lead Instructor, I want to complete a digital post-session debrief shortly after an activity concludes (recording hours, participant counts, weather, kit used, and confirming actual attendees) so that we maintain accurate usage logs and attendance rosters.

## Acceptance criteria

- Automatically triggers a reminder nudge to the lead instructor after a session window concludes (incorporating an appropriate grace period, such as one hour).
- The nudge is about one booking and may set `References` to that booking's current revision. The subject can stay distinct from the invite and update subjects.
- Provides a structured debrief form to capture operating hours, total participant numbers, weather conditions, and associated equipment sets.
- Integrates attendee confirmation directly with the booking's existing calendar attendance records to verify who actually showed up.

## Progress

- **Done (Phase A):** Past-tense attendance labels via twin enum + cast; revisitable **Confirm attendees** rollcall (lead or manager, from session calendar day); per-attendee `attendance_confirmed_at`; ternary Skip / Attended / Did not attend; iMIP refresh on status change only (no attendance-recorded mail).
- **Done (Phase B):** `TeamFeature::Debrief` (stores `enabled` + `activated_at`; chase/outstanding only for sessions ending on/after activation; Complete debrief still offered for older sessions); EquipmentSet Teams CRUD with ranked Equipment Set Groups (default **Equipment**, sets table and debrief checklist grouped by group; manager or system admin, not via manager role); Complete debrief modal; booking debrief infolist; Teams **Debriefs** list/view resource; tentative confirm-on-rollcall/debrief when feature on; lead focused nudges + combined-digest outstanding; lead Debriefs due widgets; manager Outstanding debriefs widget (Needs lead already covers no-lead tentatives).
- **Done (Phase C):** Instructor experience logbook — see [US-43](US-43-instructor-experience-logbook.md).
- **Remaining:** Phase D admin≠manager audit.
