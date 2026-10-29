---
id: US-64
title: "Booking Other Venue"
status: not-started
area: bookings
scope: extra
---

## Story

As an Activity Manager, I want to book a one-off place that is not in the venue catalog, so a hired hall or similar session does not force a new catalog venue.

## Acceptance criteria

- The booking venue select keeps the team’s catalog venues and adds an ungrouped **Other** option (Pass cupboard vs Members is the pattern).
- Other does not create a `Venue` row. Give-a-venue, team defaults, and the admin catalog stay catalog-only. [US-49](US-49-system-administration.md) still forbids inventing a catalog venue from the booking form.
- Choosing Other captures a name, the same structured postal fields, and optional OSM pin on that booking (reuse `App\Support\Location` / `Coords` from [US-63](US-63-venue-address-and-coordinates.md)).
- Booking `location` and `coords` from US-63 are the single “where is this session?” path for the booking view, public preview, RSVP page, mail, and later ICS. Catalog bookings keep delegating to the venue.
- Changing between a catalog venue and Other, or changing Other’s name, address, or pin, counts as a location change for iCalendar SEQUENCE.

## Refinement

Storage, `venue_id`, and keys are not decided. Candidates to evaluate rather than assume:

- Columns on `bookings` versus a booking-owned location record.
- Whether `venue_id` is nullable for Other, or some other sentinel that is not a catalog row.
- Keys: Other has no key requirements (activity+venue with no venue), or “place” is split from “which keys this session needs.”
- Who can set or edit Other (manage create/edit only is the likely default).

Do not treat those bullets as acceptance criteria until the product owner chooses among them (or a mix).
