---
id: US-64
title: "Catalog Venues and Private Booking Places"
status: done
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want to add a reusable catalog venue when I need a hall, and to book a private address that stays off the catalog, so a hired hall can be shared and a home meeting cannot.

## Acceptance criteria

### Catalog venues

- The booking venue select is searchable and grouped: this team's catalog venues, then the rest of the catalog in an optgroup. Catalog rows are `venues` with `booking_id` null. Choosing a catalog place the team does not yet have gives it to the team.
- Filament `createOption` (`+`) on that select, and Create on Manage Venues, add a catalog venue through a three-step flow: enter details, review similar venues, then confirm. A selected venue can be inspected before use; Back returns to the similar-venue list or the new venue details. The flow reuses the name, postal fields, and optional OSM pin from [US-63](US-63-venue-address-and-coordinates.md). Creating or using an existing venue attaches it to the current team. Give a venue on the Venues list is for system admins only, so managers always go through the duplicate-checking flow.
- Catalog venue identity is soft-unique on case-insensitive name plus postcode among `booking_id` null rows. Same name is allowed only when the new venue has a postcode and every existing namesake either has no postcode or a different one. Creating or saving without a postcode while a namesake exists is refused, with the error on postcode. Similar matches are still shown for review; create-anyway remains when the soft-unique rule allows it. Venue selects and similar-option labels include a place hint (city+postcode, county+postcode, postcode, city, county, or street). There is no unique index on `venues.name`.
- Admin venues, Give a venue, Manage Venues, lock-requirement picks, and catalog search ignore rows with a `booking_id`.
- One team uses a catalog venue: that team's managers can edit name, address, pin, and notes. Who created it does not matter. Two or more teams use it: details lock to system admins. Manage still shows **Edit**, disabled, with a tooltip that they should contact the site admin to make changes. Do not hide the action. Same disabled-plus-tooltip pattern as Confirm when no lead is available.
- Teams can remove a catalog venue from their team. They cannot delete the catalog row. Delete stays admin-only.

### Private booking places

- The booking venue select also has an ungrouped **Other** (Pass cupboard vs Members is the pattern). Choosing it reveals name, postal fields, and optional pin on the booking form, not a catalog create modal.
- Saving upserts a `venues` row with `booking_id` set to this booking and points `bookings.venue_id` at it. Switching to a catalog venue deletes that private row. Private rows are not attached via `team_venue`.
- Private names are not unique across bookings; two sessions may both be "Home". One private row per booking (`booking_id` unique when set).
- Private venues are always editable on the manage booking form. They are not on the Venues list.
- Public summary and Open Graph keep showing the venue **name** only ([US-12](US-12-public-booking-summary.md)). Postal address and map stay off that page. Logged-in booking views, mail, and calendars use Booking `location` / `coords` from the venue, as [US-63](US-63-venue-address-and-coordinates.md) and [US-65](US-65-ics-precise-location.md) already do.

### Shared rules

- Every booking has a real `venue_id`. There are no postal columns on `bookings`. [US-49](US-49-system-administration.md) still forbids creating an **activity** from the booking form.
- Booking validation accepts a team catalog venue or this booking's private venue. A team with no catalog venue can still create a booking once a manager can add a catalog venue or Other from the form.
- Keys stay activity plus catalog venue. A new catalog hall has no keys until an admin records lock requirements. Private venues are not offered on the admin lock form; a private booking needs no keys.
- Changing `venue_id` counts as a location change for iCalendar SEQUENCE. Editing a catalog venue's address does not by itself bump every booking's SEQUENCE. Editing this booking's private venue name, address, or pin does.
- The migration that adds `venues.booking_id` also reviews venue indexes: unique nullable `booking_id`, and a `(booking_id, name)` index for catalog lists and the software unique-name check. Do not unique `name`. Do not index postal or lat/long. Do not add a second `booking_id` index if the unique constraint already covers it. Confirm `bookings.venue_id` and `team_venue.venue_id` keep their foreign-key indexes.

## Progress

Done. Managers can create catalog venues, review and adopt similar venues through a wizard, and use catalog venues from other teams; same-name catalog rows are refused when the new place has no postcode or shares a postcode, while a new postcode against a blank namesake is allowed. Selects show a place hint so twins can be told apart. Private booking places are scoped to one booking, remain out of catalog and team pickers, can repeat names across bookings, and are updated or removed with their booking. Booking location, map, public-summary privacy, and iCalendar sequence behavior are covered by feature tests.
