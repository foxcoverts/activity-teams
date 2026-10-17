---
id: US-45
title: "Outgoing Email Delivery Tracking & Bounce Management"
status: not-started
area: communications
---

## Story

As an Activity Manager, I want the system to track outgoing email delivery status and flag bounced or rejected addresses so that I can proactively identify communication blind spots and update broken contact details before team members miss critical invitations.

## Acceptance criteria

- Logs outgoing email delivery states (such as sent, delivered, or bounced) against system communications.
- Each send keeps its own `Message-ID`. Several booking notices can share one `References` value, so a bounce is tied to the `Message-ID`, not to that revision id.
- Automatically flags or alerts managers when a team member's email address triggers a permanent bounce (for example, mail delivery failure or rejection response).
- Provides a visibility view on member profiles or management dashboards to highlight invalid or failing email addresses.
