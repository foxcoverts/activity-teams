---
id: US-65
title: "ICS Precise Location"
status: done
area: communications
scope: extra
---

## Story

As a Team Member, I want my calendar feed to carry the booking’s postal address and map pin so Apple Calendar (and other clients that honour GEO) can show where the session is.

## Acceptance criteria

- Depends on [US-63](US-63-venue-address-and-coordinates.md). Uses Booking `location` / `coords` (`Location::addressArray()`, `Coords`), not `$booking->venue->name` only.
- `LOCATION` is a comma-separated string: venue name (if set), then postal parts; name alone when there is no address. Apple still gets the name as `X-TITLE`; `VLOCATION` ADDRESS keeps newline-joined postal lines. ICS LOCATION omits the country name when the code is `GB`; booking views keep `addressArray()` unchanged.
- When both coordinates are set, emit RFC 5545 `GEO`, eluceo’s `X-APPLE-STRUCTURED-LOCATION`, `LOCATION;ALTREP=` as an OpenStreetMap URI for that pin, Outlook `X-MICROSOFT-LOCATION*` properties when parts are set, and RFC 9073 `VLOCATION` (NAME, GEO, ADDRESS). Never emit empty `GEO` or empty ALTREP.
- Name-only LOCATION remains valid when address and pin are blank.
- Feeds, invites, and per-booking `.ics` downloads all use the same factory.
- This is not [US-15](US-15-inbound-calendar-response-parsing.md) (inbound iMIP replies). [US-13](US-13-subscription-ics-feeds.md) stays done: feeds, tokens, CLASS:PRIVATE, and RELATED-TO are complete; LOCATION is now precise via this story.

## Progress

`BookingIcsFactory` builds LOCATION from booking `location` / `coords`, with GEO, Apple structured location, Microsoft fields, OSM ALTREP, and VLOCATION. Feeds stay METHOD-less; booking mail attaches METHOD REQUEST/CANCEL from the same factory.
