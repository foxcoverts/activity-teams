---
id: US-04
title: "Multi-Venue & Location-Based Management"
status: partial
area: qualifications
---

## Story

As an Activity Manager, I want to associate activities and bookings with specific physical locations (such as the main campsite or an off-site Scout hall range) so that access permissions and keys are correctly context-scoped per venue.

## Acceptance criteria

- Supports defining multiple active venues or centres outside the primary campsite.
- Bookings can be assigned to a specific venue, which automatically tailors the required access controls, key rings, and travel or warden coordination expectations.
- Member lists and access indicators can filter or show relevant venue authorisations (for example, distinguishing who has keys to the off-site shooting range versus the on-site climbing tower).

## Progress

A system admin maintains the controlled venue list ([US-49](US-49-system-administration.md)). A team is given venues from that list, and a booking picks from its team's venues. A key ring is one key per activity, and that key may belong to a team or to the site. Address, geo location, and the key ring are still open. The app can store more than one venue.
