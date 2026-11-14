---
id: US-81
title: "Combined / Composite Catalog Activities"
status: not-started
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want to book a combined session (for example Climbing & Abseiling) without maintaining a duplicate catalog activity whose qualifications and keys drift from the parent activities, so that staffing and venue access stay correct when either parent changes.

## Acceptance criteria

These apply only if this story is built. Until then, use the interim practice in Progress.

- A catalog activity may declare component activities (composition). The booking still chooses **one** activity and one audience; it does not multi-select activities.
- For a composite at a given audience, a person is Qualified (and may Lead) only when they meet **every** component’s activity–audience pairing ([US-02](US-02-activity-specific-qualification-staffing.md), [US-66](US-66-audience-qualification-requirements.md)).
- Required locks and debrief equipment for a composite are the **union** of the components’ requirements for the booking’s venue / team sets.
- Nested or cyclic components are forbidden. Each component must offer the booking’s audience.
- Display title remains the composite catalog name, overridable with `activity_label` ([US-68](US-68-booking-other-activity.md), [US-77](US-77-required-activity-label.md)).

## Progress

**Not built. Deliberately deferred (November 2026).**

**Interim practice:** book a single catalog activity (Climbing or Abseiling) and set **Activity name** (`activity_label`) when the session is a combined Climbing & Abseiling outing. Qualifications, keys, and equipment stay on that catalog activity.

**Rejected for now:** booking multi-select of several activities. It would spread ambiguous Qualified/Lead rules through forms, `metBy`, keys, debrief, and calendars.

**Preferred if revisited:** thin catalog composition as above, not multi-select on the booking.

**Reopen when:** maintaining a duplicated “Climbing & Abseiling” activity (or a second real combo) causes real sync pain on qualifications or keys.
