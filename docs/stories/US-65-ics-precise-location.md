---
id: US-65
title: "ICS Precise Location"
status: not-started
area: communications
scope: extra
---

## Story

As a Team Member, I want my calendar feed to carry the booking’s postal address and map pin so Apple Calendar (and other clients that honour GEO) can show where the session is.

## Acceptance criteria

- Depends on [US-63](US-63-venue-address-and-coordinates.md). Uses Booking `location` / `coords` (`Location::addressArray()`, `Coords`), not `$booking->venue->name` only.
- `LOCATION` is the joined `addressArray()` (else the name), with newlines between postal parts; the name is the Apple `X-TITLE`.
- When both coordinates are set, emit RFC 5545 `GEO` and eluceo’s `X-APPLE-STRUCTURED-LOCATION` ([climbing-team#15](https://github.com/foxcoverts/climbing-team/issues/15)). Never emit empty `GEO`.
- Name-only LOCATION remains valid when address and pin are blank.
- Feeds, invites, and per-booking `.ics` downloads all use the same factory.
- This is not [US-15](US-15-inbound-calendar-response-parsing.md) (inbound iMIP replies). [US-13](US-13-subscription-ics-feeds.md) stays done: feeds, tokens, CLASS:PRIVATE, and RELATED-TO are complete; LOCATION remains the venue name until this story.

## Refinement

Client encoding is not decided beyond the criteria above. Notes to evaluate rather than assume:

- RFC 5545 `LOCATION` / `GEO` (`lat;lng`). Optional `LOCATION;ALTREP=` OSM URI.
- Apple `X-APPLE-STRUCTURED-LOCATION` is the first target (issue 15; eluceo already emits it when geo is set).
- Google Calendar maps from LOCATION + GEO; never emit empty GEO. No public `X-GOOGLE-` geo field.
- Outlook `X-MICROSOFT-LOCATIONSTREET` (join `street` + `street2`) / `CITY` / `STATE` / `POSTALCODE` / `COUNTRY` and lat/lng map from columns already stored. Emitting those properties is still a later extra. Do not invent Bing `LOCATIONURI`. vCard ADR LABEL is the joined `addressArray()`; street lines go in the street component.
- RFC 9073 `VLOCATION` if a client we care about needs it; eluceo 2.x does not emit it today.
- Whether `addressArray()` should omit the country name when the code is `GB`, so ICS LOCATION matches UK postal order.

Do not treat those bullets as acceptance criteria until the product owner chooses among them (or a mix).
