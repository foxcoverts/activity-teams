---
id: US-40
title: "Booking & Content Commenting Threads"
status: not-started
area: audit
scope: required
---

## Story

As a team member or manager, I want to post comments and updates directly on bookings so that we can communicate announcements, setup notes, or logistical discussions in a shared space.

## Acceptance criteria

- Provides an interactive comment thread interface directly on bookings and interactive content.
- Automatically integrates comment submissions into both the booking view and the central activity or changes feed as a discussion wall.
- A comment email about a booking sets `References` and `In-Reply-To` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`, so it joins the latest invite or update. Comments on interactive content do not use that header. Gmail also needs the comment subject to match that mail before it groups them.

## Progress

[US-61](US-61-notification-settings.md) already stores and shows `bookings.comments`. Gate comment mail with `MailPreference::allows` on that channel for the booking, and include the subject-capable preferences footer. Do not send `List-Unsubscribe`.
