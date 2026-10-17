---
id: US-11
title: "Automated Team Leader Assignment Notifications"
status: not-started
area: bookings
---

## Story

As a team member, I want to receive an automated notification email when designated as a team leader so that I am immediately aware of my responsibilities.

## Acceptance criteria

- Assigning a user as a team leader triggers an email outlining role context, expectations, and relevant booking links.
- When the mail is about one booking, it may set `References` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`. The subject can stay distinct from the invite and update subjects.
