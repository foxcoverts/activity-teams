---
id: US-40
title: "Booking & Content Commenting Threads"
status: done
area: audit
scope: required
---

## Story

As a team member or manager, I want to post comments and updates directly on bookings so that we can communicate announcements, setup notes, or logistical discussions in a shared space.

## Acceptance criteria

- Provides an interactive comment thread on booking view pages, in a Comments panel under the booking details. It is not part of Recent activity. Other resources can reuse the polymorphic comments model later; they are not wired yet.
- Anyone who can respond on that booking can post, including an attendee who is not a current team member. Signed-out people cannot comment. Past and cancelled bookings stay closed.
- Soft-deleted comments stay in the feed, ghosted, for the author and team managers. Other viewers never see them. The author or a manager can then remove them completely. Only a manager can restore. Authors can edit live comments they wrote.
- A comment email about a booking sets `References` and `In-Reply-To` to that booking's current revision, `booking-{id}-sequence-{n}@{host}`, so it joins the latest invite or update. The subject is `Re: :activity at :venue, :start`. Comments are sent immediately when `bookings.comments` (and parent `bookings.mail`) allow. Gmail also needs the comment subject to match that mail before it groups them.
- Mail and inbox links open the member booking view with a `comment` query. That row is shown first, with older comments hidden until **Show older comments**.

## Progress

The booking comments panel, moderation, inbox rows, and immediate threaded mail are in this repository. Comment bodies render with inline markdown; the composer stays plain text. Nested replies, other commentables, US-38's team-wide feed, and logging comments as activity events stay out of this slice. [US-61](US-61-notification-settings.md) already stores and shows `bookings.comments`. Gate comment mail with `MailPreference::allows` on that channel for the booking, and include the subject-capable preferences footer. Do not send `List-Unsubscribe`. When that mail is queued, mark the matching inbox row read (`markReadForDelivery`) so it does not stay in the unread badge; preference-blocked comments stay unread. See [US-67](US-67-member-notification-inbox.md).
