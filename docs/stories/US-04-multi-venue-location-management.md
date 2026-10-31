---
id: US-04
title: "Multi-Venue & Location-Based Management"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want to associate activities and bookings with specific physical locations (such as the main campsite or an off-site Scout hall range) so that access permissions and keys are correctly context-scoped per venue.

## Acceptance criteria

- Supports defining multiple active venues or centres outside the primary campsite.
- Bookings can be assigned to a specific venue, which automatically tailors the required access controls, key rings, and travel or warden coordination expectations.
- Member lists and access indicators can filter or show relevant venue authorisations (for example, distinguishing who has keys to the off-site shooting range versus the on-site climbing tower).

## Progress

A system admin maintains the controlled venue list ([US-49](US-49-system-administration.md)). A team is given venues from that list, and a booking picks from its team's venues. The app can store more than one venue.

A person holds a key. A key opens one or more locks. A lock is not one per activity, and it has no venue of its own. A booking requires the locks marked for its activity at its venue. Holding a key that opens one of those locks, or a lock that also opens one, gives a person that access. A manager can see every holder of a key that opens a lock that team needs, including someone who is not a current member, and only that person's name.

Travel expectations and warden coordination are manual. They are not screens in this system. The Keys list is what a manager uses while doing that work. A lock that no key opens stays on the admin Locks catalog.

A manager opens Keys on a booking to see the locks that activity needs at that venue, and the keys that open them. The roster and the invite list mark who holds a key that opens one of those locks, and who holds a key that this booking does not need. The invite list can be narrowed to key holders. The team members list marks who can open a team-relevant lock. That badge, and the Locks list on the member, show those locks and what they are required for. The list can be filtered by those locks. Address and geo location are not part of these criteria.
