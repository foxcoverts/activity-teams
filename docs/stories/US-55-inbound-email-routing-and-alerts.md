---
id: US-55
title: "Inbound Email Routing & Failure Alerts"
status: done
area: communications
scope: required
---

## Story

As the person responsible for the system, I want email replies sent to the system to be routed or retained and processing failures to be surfaced, so that useful correspondence is not lost and I can investigate problems such as calendar replies not updating attendance.

## Acceptance criteria

- Replies sent to addresses monitored by the system are not silently discarded. They are routed to a defined destination or retained for review when automatic routing is not known or available.
- Unrouted or unprocessable messages are retained in a restricted in-app review queue, and the system owner is alerted.
- Recognized message types are routed according to agreed behavior. Inbound calendar replies are handled by [US-15](US-15-inbound-calendar-response-parsing.md); other reply destinations are decided during refinement.
- An iMIP `REPLY` from an unmapped address (booking `VEVENT` or task `VTODO`) is a recognized-but-unassociated case: keep it in the review queue until [US-62](US-62-unmapped-calendar-reply-identity.md) associates it.
- A reply to a booking update could become a comment on that booking, and a reply to a news post could be forwarded to that team's managers. These are examples to evaluate, not behavior assumed by this story.
- When an inbound email fails to process or cannot be routed, the issue is surfaced to the designated system operator rather than being silently lost.
- The operator receives enough safe diagnostic context to identify and investigate the issue without requiring a general-purpose mail-log interface.
- Refinement defines the alert channel, retention period and review process for queued messages, and how sensitive message content is protected.
- Successfully routed messages do not generate failure alerts.

## Progress

Authenticated webhook payloads are stored as `InboundMail`. System admins review them in Admin → Inbound mail (mark reviewed / delete). Queued and failed messages stay until a system admin deletes them; there is no automatic purge. Unauthenticated payloads are never stored. Member-facing mail uses plus-address Reply-To (`booking+`, `task+`, `key+`, `teams+`); manager/admin/account mail stays on `MAIL_FROM_ADDRESS`. Unhandled messages are not emailed one at a time. The digest sends system admins one coalesced “N need review” nudge linking to that queue, then stays quiet until a newer unhandled message arrives. From-address / unknown local-part replies rely on the existing `team@` / catch-all inboxes, so there is no extra developer ping. Successful REPLY/REFRESH do not alert. News and booking-comment routing stay out of this slice ([US-54](US-54-team-news-posts.md), [US-40](US-40-booking-commenting-threads.md)).
