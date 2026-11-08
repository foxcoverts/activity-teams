---
id: US-77
title: "Required Activity Label on Catalog Activity"
status: done
area: bookings
scope: required
---

## Story

As a System Admin, I want a catalog activity to require a custom booking name, so access-style sessions (for example Climbing Access) keep keys and staffing rules while every booking shows a proper title such as Tower Inspection or Kit Checks.

## Acceptance criteria

- An admin can mark a catalog activity as requiring an activity name on each booking (`requires_activity_label`). The catalog name stays for keys and qualifications; it is not enough as the booking title when the flag is set.
- On the booking form, choosing such an activity always shows a required **Activity name** field (no pencil gate). Saving without a name is refused in the form and again on the model.
- The booking keeps `activity_id` and `audience_id`. Qualifications and keys behave like any other catalog activity ([US-66](US-66-audience-qualification-requirements.md), [US-03](US-03-booking-venue-access-logistics.md)).
- Display, mail, and calendars still use `activityTitle()` ([US-68](US-68-booking-other-activity.md)). Managers still see **Original activity: …** when a custom name is set.
- Normal catalog activities keep optional **Set activity name** behaviour from [US-68](US-68-booking-other-activity.md). **Other** is unchanged and still cannot carry keys.

## Progress

Done. Admin activities have **Require activity name on bookings**. Choosing such an activity on the booking form always shows a required **Activity name**; the model refuses a blank label. Catalog activity, audience, keys, and qualifications stay attached. Optional pencil labelling for other catalog activities is unchanged ([US-68](US-68-booking-other-activity.md)).
