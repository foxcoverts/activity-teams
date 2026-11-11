---
id: US-67
title: "Member Notification Inbox"
status: done
area: communications
scope: required
---

## Story

As a team member, I want deferrable notices in an inbox, with email only when I have not already read them, so that calendar and account mail stay immediate and everything else can wait.

## Acceptance criteria

- Urgency decides delivery. Calendar iMIP (invite, update, confirm, cancel, and a resent calendar copy) and account access (password reset, verify-email, and a team invitation) send immediately. If a preference blocks an optional or immediate booking send and [US-76](US-76-booking-calendar-channel-and-force-send.md) has no open calendar channel for that booking attendee, the app still writes an inbox item and attaches no calendar file. A forced resent calendar copy (guest-list Send to Email or inbound `REFRESH`) always sends ICS and never uses that inbox sink.
- Deferrable notices are recorded in the member's inbox and are not emailed until the digest job. That includes attendance recorded by a manager, a key passed to or from a holder, a key returned to the cupboard, lead needed, a declined invitation, the last manager leaving, a key holder deleting their account, and team news.
- The inbox is one page, linked from the user menu on the member, manage, and admin panels, and it shows that person's rows. The top bar also shows an envelope next to the user icon, with the unread count on it. Opening the list does not mark anything read. Following a row's link marks it read. Mark read on the selected rows does the same without leaving. Sending that notice's own email immediately (comment mail when preferences allow) also marks it read. All of those set the read time and the digest time. Stamping only the digest time skips a later digest send but leaves the unread badge. The menu badge and the envelope badge are the unread count.
- Each row is one sentence. Every sent notice has a URL, and the row opens the view page, which marks it read and redirects there. The view page does not render the notice. A notice that cannot have a destination is out of scope until `ViewNotification` is changed to show the message instead of redirecting. The emailed message, when the digest sends it, stays the existing mail.
- A last-manager row links to the admin teams list with the Managers filter set to None. A key-holder row is one per team and links to that team's manage keys list filtered to the person who left. A declined team invitation links to that placeholder on the team's manage members page. A declined site invitation links to the placeholder on the admin user view.
- The digest job has no time-of-day rule. The scheduler is the only cadence, initially daily. A row already read, or already included in an earlier run, is left out. A second run with nothing outstanding sends nothing.
- One emailable outstanding item, and no rota change, sends that existing message and marks the row read. More than one, or any emailable item together with a rota change, sends one summary and does not mark those rows read. A rota change alone sends the rota section only.
- Channels that are off still write an inbox row. Those rows do not by themselves cause a send. Manager always-on items are always recorded and always emailable for the digest. They are not immediate SMTP.
- Unhandled inbound mail does not send one email per message. System admins get one coalesced review nudge. After that nudge is emailed, a later run stays quiet until a new unhandled message arrives.
- A correlated bounce clears the read time and the digest time on the matching inbox row. There is no bounce log or dashboard in this slice.
- The Filament notification bell stays off. Inbox payloads do not use the Filament notification format.

## Progress

The inbox, the digest job, the preference sink, and the coalesced inbound nudge are in this repository. [US-17](US-17-schedule-digest-changelog.md) is the rota section of the same job. [US-45](US-45-email-delivery-bounce-tracking.md) is still the full bounce product. News is in the deferrable set via [US-54](US-54-team-news-posts.md). Preference-blocked task mail writes a held inbox row the same way bookings do ([US-79](US-79-task-assignees-and-imip.md)). [US-40](US-40-booking-commenting-threads.md) still writes an inbox row for each comment, but comment mail is immediate so it can thread with calendar mail; those rows are marked read and digest-notified when that mail is queued, the same as a one-item digest send. Invitation nudges stay out until that story lands.
