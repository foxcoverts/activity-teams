---
id: US-71
title: "Dashboard: Unread Inbox Preview"
status: done
area: communications
scope: extra
---

## Story

As a team member, I want a short preview of my unread notices on my dashboard, so that I notice important changes and can clear them quickly.

## Acceptance criteria

- Shows the member's five most recent unread [US-67](US-67-member-notification-inbox.md) inbox rows, with the total unread count. Ordering and any pinning follow the inbox exactly; if the inbox later pins or prioritises a notice type, the dashboard does too.
- The dashboard preview and the top bar envelope badge read the same unread count source, so they cannot disagree.
- Each row is the existing one-sentence notice. Following a row uses the existing view route, which marks it read and redirects to its destination.
- Rows can be marked read without leaving the dashboard, using the same read stamps as the inbox so the digest and the badge stay consistent.
- Marking notices read from the dashboard updates the sidebar Inbox badge and the top bar envelope badge straight away.
- Viewing the dashboard never marks anything read.
- The section is titled **Inbox** and is the first section on the dashboard.
- A **Mark all read** action marks every unread notice. When more unread notices exist than the preview shows, a second action, **Mark shown read**, marks only the rows in the preview, exactly the rows the member saw rather than whatever is newest when they confirm. When five or fewer are unread, only Mark all read is offered.
- A link to the full inbox is available.
- Marking a row read removes it from the preview and the next unread row takes its place, so the preview keeps refilling until nothing unread is left. It does not imply that any action the notice describes has been completed.
- Only the member's own rows appear. Deleted and already-read rows do not.
- There is no maximum age: an unread row stays in the preview however old it is.
- The section is hidden when there are no unread rows, per [US-69](US-69-member-dashboard.md).
