---
id: US-61
title: "Notification Settings"
status: done
area: communications
scope: required
---

## Story

As a team member, I want to choose which emails I receive — globally and, for bookings, for a specific session — so that I can quiet mail that is not always-on without losing the in-app record.

## Acceptance criteria

- System defaults, the member's global settings, and per-subject overrides resolve in that order. An unset value inherits the layer below. User-controlled channels default to on.
- A member can set global **Bookings** (`all mail`, `updates`, `comments`, `digest`) and **Keys** (`all mail`) from a Notifications page in the user menu. Last-manager, key-holder leaving, and a declined invitation are always recorded and always emailable for the digest. They are not immediate mail, and they are not toggles. Account mail (invitations, password reset, verification) is always on and has no preference controls.
- `all mail` off for bookings skips invite, update, comment, and attendance-recorded email, except where [US-76](US-76-booking-calendar-channel-and-force-send.md) keeps ICS lifecycle mail going for an open calendar channel on that booking. The attendance override and a blocked invite or update still save an inbox item when there is no channel ([US-67](US-67-member-notification-inbox.md)). Invites have no separate toggle. Inbox rows are written either way when mail is skipped. Neither `bookings.digest` nor `bookings.mail` has to be on for an inbox item to exist.
- `updates` covers changes, confirms, cancels, and lead-responsibility copy, and requires `all mail`, with the same calendar-channel exception in US-76. Lead needed is recorded in the inbox and emailed on the digest only when updates are allowed.
- `comments` is stored as a simple on/off (not all/reply/leader/none). Comment mail is sent immediately when [US-40](US-40-booking-commenting-threads.md) allows it, so it can thread with calendar mail.
- Optional mail includes a preferences footer. Always-on mail does not. The footer opens a public, prefetch-safe page (GET never writes) authenticated by that member's mail-preference token, not a Filament login.
- For bookings, that page defaults to **this booking**, with a switch to **all bookings**. Saving the subject creates or updates an override; saving global updates only that group's global channels. Keys have no per-ring override this story: the footer opens global keys.
- The logged-in Notifications page lists existing per-subject overrides so they can be edited or removed. There is no control to add a subject. Preference records are private to the member.
- Booking and key mail do not send `List-Unsubscribe`. That header is for later broadcast channels such as news.

## Progress

The catalog, global Notifications page, public manage page, optional-mail footers, and gates on immediate optional senders are in this repository. The invite list shows a **No email** badge when `bookings.mail` is off; the guest list does not. `bookings.digest` is shown and gates the rota section of [US-67](US-67-member-notification-inbox.md). `news.mail` is shown. [US-79](US-79-task-assignees-and-imip.md) adds a **Tasks** group (`tasks.mail`, `tasks.updates`) with the same calendar-channel exception pattern as bookings. Off channels still land in the inbox unless US-76’s calendar channel keeps ICS mail sending. Last-manager, key-holder leaving, and a declined invitation are always recorded and digestable. Account mail stays immediate.
