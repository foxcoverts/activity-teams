---
id: US-45
title: "Outgoing Email Delivery Tracking & Bounce Management"
status: done
area: communications
scope: extra
---

## Story

As an Activity Manager, I want the system to track outgoing email delivery status and flag bounced or rejected addresses so that I can proactively identify communication blind spots and update broken contact details before team members miss critical invitations.

## Acceptance criteria

- Logs outgoing email delivery states (such as sent, delivered, or bounced) against system communications.
- Each send keeps its own `Message-ID`. Several booking notices can share one `References` value, so a bounce is tied to the `Message-ID`, not to that revision id.
- Automatically flags or alerts managers when a team member's email address triggers a permanent bounce (for example, mail delivery failure or rejection response).
- Provides a visibility view on member profiles or management dashboards to highlight invalid or failing email addresses.

## Progress

Done for available technology. Outbound SMTP stays on ForwardEmail; a `MessageSending` listener records `outbound_mails` (`sent` / `bounced`) with a normalised `Message-ID`. Bounce webhooks land at `/api/webhooks/forwardemail/bounces` and map into a provider-agnostic `BounceReport` / `ProcessMailBounceAction`. Hard categories (`block`, `recipient`, `spam`, `virus`) flag the member and notify active team managers with a link to the member profile; soft categories log only. Matching inbox rows release via `outbound_message_id`. Changing the member's email clears the bounce flag. Profile contact details show a bounced badge and helper text. Inbound mail moves to `/api/webhooks/forwardemail/mail` with `/api/mail` kept as a deprecated alias.

**Deferred:** true `delivered` receipts (ForwardEmail has no delivery webhook for SMTP), click/open tracking, and a dedicated manager bounce triage list ([US-80](US-80-manager-bounce-triage-dashboard.md)). Remediation uses the existing edit-email / invite paths.
