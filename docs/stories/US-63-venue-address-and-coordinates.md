---
id: US-63
title: "Venue Address and Coordinates"
status: done
area: qualifications
scope: extra
---

## Story

As a System Admin, I want to store an optional postal address and map pin on a catalog venue so members and managers can see where a session is, and so a later calendar export can carry a precise location.

## Acceptance criteria

- A system admin can save optional street, address line 2, city, county, postcode, and country on a catalog venue. Country is an ISO 3166-1 alpha-2 code (default `GB`), not a free-text name.
- A system admin can add or remove an optional OpenStreetMap pin. Coordinates are stored from the pin, not typed. Address parts may be set without a pin, and the reverse.
- Who can create or edit a venue, including a private booking place, is [US-64](US-64-booking-other-venue.md). This story is the postal parts and pin.
- On member and Teams booking views, the composed postal address is shown when any part is set. The map is not shown until someone opens View Map, which then shows the pin. View Map is hidden when there is no pin.
- Public booking views do not show a map. They may show the venue name ([US-12](US-12-public-booking-summary.md)).
- ICS export is unchanged; precise location in feeds is [US-65](US-65-ics-precise-location.md). A private booking place is [US-64](US-64-booking-other-venue.md).

## Progress

Catalog venues store Outlook-shaped postal parts and WGS84 coordinates. Display goes through `App\Support\Location` (`addressArray()`) and `App\Support\Coords`. Bookings take `location` and `coords` from the venue because every booking has a venue row; a private place in [US-64](US-64-booking-other-venue.md) is also a venue, not columns on `bookings`.

The admin form MapPicker is behind a Map location toggle: off leaves or clears coordinates; on places a pin (defaulting to the UK camera if none is stored). Venue view pages show a MapEntry only when both coordinates are set. Member and Teams booking infolists show the name and address, with View Map opening a map modal when a pin exists. Public preview and RSVP pages have no map. Precise location in calendar export is [US-65](US-65-ics-precise-location.md) and is done; this story still does not own that export. Manager catalog create/edit and a private booking place remain [US-64](US-64-booking-other-venue.md).
