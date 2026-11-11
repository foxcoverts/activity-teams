---
id: US-15
title: "Inbound Calendar Response & Webhook Parsing"
status: done
area: communications
scope: required
---

## Story

As an Activity Manager, I want the system to reliably parse incoming calendar email responses (including complex verbs and signature comments) so that attendance records update automatically.

## Acceptance criteria

- Applies an inbound iMIP `REPLY` only when the attendee maps to a known person. Unmapped addresses are retained by [US-55](US-55-inbound-email-routing-and-alerts.md); identity linking is [US-62](US-62-unmapped-calendar-reply-identity.md). The same rule covers booking `VEVENT` and task `VTODO` replies.
- Parses advanced calendar verbs like COUNTER and REFRESH.
- Strips extraneous mail-server signature COMMENT blocks.
- Identifies the booking from the calendar UID, `booking+{id}@{host}`, and the task from `task+{id}@{host}`. Those UIDs do not include the sequence. The email `References` value (with sequence) is a different identifier and does not select the booking or task.
- Records that an attendance or task-assignee response arrived as an iMIP `REPLY`, separately from a response made in the app.
- A later booking cancellation uses that record. Someone who declined a booking is sent the cancellation only when their decline was an iMIP `REPLY`, so their calendar can apply `METHOD:CANCEL`. A decline made in the app is not sent a cancellation.

## Progress

Inbound ForwardEmail webhook (`POST /api/mail`) authenticates with a shared secret, parses calendar attachments with `sabre/vobject`, and applies booking `REPLY` PARTSTAT through `RespondToBookingAction` with `via: imip`. [US-79](US-79-task-assignees-and-imip.md) uses the same pipeline for task `VTODO` replies via `RespondToTaskAssigneeAction`. Unmapped attendees and unrecognized methods stay in the Admin inbound-mail queue ([US-55](US-55-inbound-email-routing-and-alerts.md)); associating those addresses remains [US-62](US-62-unmapped-calendar-reply-identity.md) for both bookings and tasks. `REFRESH` and stale `REPLY` SEQUENCE values keep the attendance or assignee change (for REPLY) and resend the current `REQUEST`/`CANCEL` ICS with a forced send (always ICS, no held inbox). Successful `REPLY` and `REFRESH` for a mapped person open that booking or task’s calendar channel ([US-76](US-76-booking-calendar-channel-and-force-send.md), US-79). `COUNTER`, `ADD`, inbound `REQUEST`/`CANCEL`, and plain replies are recognized and queued; accepting a COUNTER stays backlog. VEVENT `COMMENT` / `X-RESPONSE-COMMENT` are ignored (climbing-team#142) rather than written as booking comments — that is the signature-COMMENT fix for this slice; a real comment product waits on [US-40](US-40-booking-commenting-threads.md). Email-button respond URLs carry SEQUENCE and warn when the booking or task has moved on, still confirm-before-save.
