---
id: US-49
title: "System Administration"
status: done
area: qualifications
scope: required
---

## Story

As a system admin, I want to set up teams and the shared catalogs they use so that team managers can run their own work without inventing activities from a booking.

## Acceptance criteria

- A system admin can create a team and must name its first manager.
- A system admin can create, edit, and delete activities.
- A system admin can create, edit, and delete catalog venues. Managers adding and editing catalog venues, and a private place on a booking, are [US-64](US-64-booking-other-venue.md).
- A team manager can give their team an activity or a catalog venue from those catalogs.
- A booking form cannot create an activity. [US-68](US-68-booking-other-activity.md) is a booking-level Other or Activity name, not a catalog row.
- System-admin status alone does not grant access to phone numbers, emergency contacts, or other personal data. Any otherwise authorized access to another person's personal details requires the acknowledgement in [US-58](US-58-personal-data-viewing-acknowledgement.md).

## Progress

A system admin can give any team its activities and venues, see that team's keys, and manage its members, roles, and qualifications, even when they do not manage the team. They still cannot create a booking or manage attendance for that team.

System admins can open the global User screens on the Admin panel to grant oversight and system-admin access (only system admins can change those flags). New oversight and system admins who do not yet have an account are invited with [US-60](US-60-oversight-system-admin-invitations.md); a brand-new first manager of a team is invited first, then chosen. Team creation and the activity, venue, and key catalogs also live on Admin, not under a Teams tenant. Their view and edit of another person's contact and emergency details requires the US-58 acknowledgement, so the personal-data criterion is met for every screen that currently shows these details. The other criteria are met and tested: a system admin creates a team and must name its first manager, only system admins create, edit and delete activities, a team manager gives their team items from those catalogs, and the booking form cannot create an activity. Manager catalog venue create/edit and private booking places are implemented in [US-64](US-64-booking-other-venue.md); booking-level Other activity and Activity name are implemented in [US-68](US-68-booking-other-activity.md).
