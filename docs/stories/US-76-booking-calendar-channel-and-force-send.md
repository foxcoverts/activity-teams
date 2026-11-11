---
id: US-76
title: "Booking Calendar Channel & Force-Send Invite"
status: done
area: communications
scope: required
---

## Story

As a team member on a booking, I want to download or email myself the current calendar invite, and I want calendar clients that already speak iMIP to keep receiving ICS updates even when I have turned optional booking mail off, so that my calendar stays in sync without useless inbox noise.

## Acceptance criteria

- The guest list header has two separate action groups: a **UserPlus** menu for manager **Invite** and **Add**, and an **ArrowDownTray** menu for attendee **Add to Calendar** and **Send to Email**.
- **Add to Calendar** and **Send to Email** are available to the signed-in user when they are on the guest list with Needs action, Going, or Maybe, the booking is not past, and it is not cancelled — including when booking mail preferences are off. They are hidden when Declined, when the user is not on the list, when the booking is past, or when it is cancelled.
- **Add to Calendar** downloads the authenticated booking `.ics`. Downloading alone does not open a calendar channel.
- **Send to Email** always sends the current calendar copy (`METHOD:REQUEST`, or `CANCEL` if the booking is cancelled) to that user, never writes a held inbox row for that send, and does not by itself mark a calendar channel.
- Inbound iMIP `REFRESH` and a stale `REPLY` resend use the same forced send path (always ICS, no held inbox).
- A per-booking attendee **calendar channel** (`booking_user.calendar_imip_at`) is set only when inbound `REPLY` or `REFRESH` is handled for that person on that booking. Outbound invite, update, cancel, and force-send do not open the channel.
- While the channel is active, lifecycle booking mail that carries ICS (invite, update, confirm, cancel) still sends even when `bookings.mail` / `bookings.updates` would block, and does not write a held inbox row for that send.
- Without a channel, optional-mail off still skips ICS and writes a held inbox item as in [US-61](US-61-notification-settings.md) / [US-67](US-67-member-notification-inbox.md).
- The channel is cleared after a successful outbound `METHOD:CANCEL` send to that attendee. An inbound Declined `REPLY` leaves the channel set; declined attendees already skip ordinary updates. Cancel inclusion for prior iMIP declines stays on `via: imip` ([US-28](US-28-booking-cancellation-reasons.md)).

## Progress

Implemented: `calendar_imip_at` on `booking_user`, mark on inbound REPLY/REFRESH, clear after outbound cancel ICS, preference bypass while the channel is active, forced resend for guest-list Send to Email and inbound REFRESH/stale REPLY, and guest-list header action groups. Cross-links [US-15](US-15-inbound-calendar-response-parsing.md), [US-61](US-61-notification-settings.md), and [US-67](US-67-member-notification-inbox.md). Task assignees use the same calendar-channel idea on `task_user` under [US-79](US-79-task-assignees-and-imip.md); this story stays booking-scoped.
