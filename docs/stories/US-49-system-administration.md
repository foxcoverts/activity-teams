---
id: US-49
title: "System Administration"
status: partial
area: qualifications
scope: required
---

## Story

As a system admin, I want to set up teams and the shared catalogs they use so that team managers can run their own work without inventing places or activities from a booking.

## Acceptance criteria

- A system admin can create a team and must name its first manager.
- A system admin can create, edit, and delete activities and controlled venues.
- A team manager can give their team an activity or a venue from those catalogs.
- A booking form cannot create an activity or a venue.
- System-admin status alone does not grant access to phone numbers, emergency contacts, or other personal data. Any otherwise authorized access to another person's personal details requires the acknowledgement in [US-58](US-58-personal-data-viewing-acknowledgement.md).

## Progress

A system admin can give any team its activities and venues, see that team's keys, and manage its members, roles, and qualifications, even when they do not manage the team. They still cannot create a booking or manage attendance for that team.

System admins can open the global User screens to grant oversight and system-admin access (only system admins can change those flags). Their view and edit of another person's contact and emergency details requires the US-58 acknowledgement, so the personal-data criterion is met for every screen that currently shows these details. The remaining criteria (creating teams with a first manager, managing the activity and venue catalogs) have not been re-checked, so this story stays `partial`.
