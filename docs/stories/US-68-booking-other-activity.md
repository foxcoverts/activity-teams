---
id: US-68
title: "Booking Other Activity"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want to book a session that is not a catalog activity, or to set a custom activity name on a catalog activity, so a meeting needs no qualifications and tower maintenance can still use Climbing's lead rules and keys.

## Acceptance criteria

- Activities stay an admin catalog. Managers give their team items from that list. The booking form has no `createOption` on the activity select. **Other** and a custom **Activity name** do not create an `Activity` row. [US-49](US-49-system-administration.md) still forbids inventing a catalog activity from a booking.
- When the team has catalog activities, the activity select lists those activities plus an ungrouped **Other** (Pass cupboard vs Members is the pattern). Choosing Other reveals a required **Activity name** field and hides audience. Saving stores that name on the booking (`activity_label`), leaves `activity_id` and `audience_id` null, and does not attach qualifications or keys.
- Other is treated like a pairing with no qualification rows: anyone who has accepted may be the lead. Confirm still needs a lead ([US-09](US-09-mandatory-lead-instructor-confirmation.md)).
- Choosing a catalog activity keeps audience and the pairing from [US-66](US-66-audience-qualification-requirements.md). Keys stay on the catalog activity and venue ([US-04](US-04-multi-venue-location-management.md), [US-64](US-64-booking-other-venue.md)).
- **Set activity name** is a pencil action next to the chosen catalog activity (tooltip: use a different name for this booking), not a standing extra row. It reveals an optional **Activity name** field with the selected catalog activity as its placeholder. Hidden by default; if a label is already stored, the field is shown so an existing labelled booking is editable.
- Members, managers, public preview, mail, and calendars use only the custom label when it is set (`activityTitle()`: `activity_label` if set, otherwise the catalog name). View and table screens show that title once as **Activity**. When a catalog activity has a custom name, managers see an info icon with tooltip **Original activity: …**; the catalog activity and audience remain attached for qualifications and keys. `group_name` stays the visiting group; it is not an activity name.
- A team with no catalog activities can still create a booking. The activity select is omitted; only the required **Activity name** field is shown, and the booking is saved as Other. When the team has catalog activities, Other remains an option on the select.
- Changing catalog vs Other, or the activity name, counts as an iCalendar SEQUENCE change, alongside today's activity, audience, venue, start, end, and status.

## Progress

Done. A team with an empty activity catalog can create a booking with only an activity name; with catalog activities, Other is on the select and **Set activity name** reveals an optional **Activity name** on a chosen catalog activity. Other stores a custom booking label without activity or audience rows and allows any accepted attendee to lead. Catalog activities retain their audience and qualification behavior. Booking view and table titles, public, mail, and calendar titles use `activityTitle()`, and activity-name changes increment iCalendar SEQUENCE.
