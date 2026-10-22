---
id: US-47
title: "Multiple Activity Teams"
status: done
area: qualifications
scope: essential
---

## Story

As an activity manager, I want bookings, members, and invite lists to belong to a team so that each team only sees and staffs its own work.

## Acceptance criteria

- A person can belong to more than one team, as a manager or a member, and a team can have more than one manager.
- A team always has at least one manager. A manager can step down, or be removed, only when another manager of that team is already in place.
- A booking belongs to one team. Its activity and venue are ones that team has been given.
- Invite and Add only offer current members of that team. Someone who is not a current member can already be on a roster.
- A member sees a booking when they are on its roster, or when they are a current member of its team and it is confirmed. A manager of its team sees it in any status. A member does not see another team's confirmed bookings.
- The Members panel is the personal, cross-team workspace for bookings and invitations a user may access. It has no current-team tenant switch.
- Team-scoped resources such as documents or news may offer a team filter within Members, but do not require switching the current tenant.
- Managers remain members and lead instructors and use Members for day-to-day member tools, as well as Manage for team management.
- The manage panel keeps one team current, and bookings, members, activities, and venues follow that team.
- The Members sidebar links to Manage only for users who can access it, and Manage provides a link back to Members.
- Leaving a team keeps attendance already recorded. They can still open those bookings, and they leave future invite lists.
- Someone with oversight can open a directory of every account. A team manager can access their team's members, including phone and emergency details, without being the lead instructor on a booking. Viewing another person's personal details requires the acknowledgement in [US-58](US-58-personal-data-viewing-acknowledgement.md), in addition to the existing access rules.
