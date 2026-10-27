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
- A member can set global **Bookings** (`all mail`, `updates`, `comments`) and **Keys** (`all mail`) from a Notifications page in the user menu. **Manager** mail is always on and is explained, not togglable. Account mail (invitations, password reset, verification) is always on and has no preference controls.
- `all mail` off for bookings skips invites, updates, comments, and manager attendance-override mail. The attendance override still saves. Invites have no separate toggle.
- `updates` covers changes, confirms, cancels, and lead-responsibility copy, and requires `all mail`.
- `comments` is stored as a simple on/off (not all/reply/leader/none). Comment mail is sent when [US-40](US-40-booking-commenting-threads.md) is built.
- Optional mail includes a preferences footer. Always-on mail does not. The footer opens a public, prefetch-safe page (GET never writes) authenticated by that member's mail-preference token, not a Filament login.
- For bookings, that page defaults to **this booking**, with a switch to **all bookings**. Saving the subject creates or updates an override; saving global updates only that group's global channels. Keys have no per-ring override this story: the footer opens global keys.
- The logged-in Notifications page lists existing per-subject overrides so they can be edited or removed. There is no control to add a subject. Preference records are private to the member.
- Booking and key mail do not send `List-Unsubscribe`. That header is for later broadcast channels such as news.

## Progress

The catalog, global Notifications page, public manage page, optional-mail footers, and gates on existing optional senders are in this repository. The invite list shows a **No email** badge when `bookings.mail` is off; the guest list does not. Reserved channels (`news.mail`, `bookings.digest`) are registered for later stories; see those files for how to use US-61. Manager and account mail stay always-on.
