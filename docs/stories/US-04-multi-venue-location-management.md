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

A system admin maintains the controlled venue list ([US-49](US-49-system-administration.md)). A team is given venues from that list, and a booking picks from its team's venues. The app can store more than one venue.

A key is one lock. It is not one key per activity, and it has no venue of its own. A key ring lists the keys on it. A booking requires the keys marked for its activity at its venue. Holding one of those keys, or a key that covers one, gives a person that access. A manager can see every holder of the keys that team needs, including someone who is not a current member, and only that person's name.

Travel expectations and warden coordination are manual. They are not screens in this system. The key list and the holder list are what a manager uses while doing that work.

The booking does not yet show the keys it requires. Member lists cannot filter or mark who holds which key. Address and geo location are not part of these criteria.
