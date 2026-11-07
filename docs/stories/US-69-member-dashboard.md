---
id: US-69
title: "Member Dashboard: What Needs My Attention"
status: done
area: bookings
scope: extra
---

## Story

As a team member, I want my dashboard to show what needs my attention, most urgent first, so that I can act on this week's commitments, notices, and outstanding responses without searching through lists.

## Acceptance criteria

- The member panel dashboard is the member's landing page and replaces the current default. It is composed of the sections in [US-70](US-70-dashboard-this-week.md), [US-71](US-71-dashboard-unread-inbox.md), [US-72](US-72-dashboard-needs-response.md), [US-73](US-73-dashboard-next-led-booking.md), and [US-74](US-74-dashboard-latest-news.md). Each section is a short preview that links to the full list; the dashboard does not duplicate booking management.
- Sections always appear in this order: Inbox, This week, Needs your response, Next booking you lead, Latest news. The order is fixed. Only visibility changes. Members cannot hide or reorder sections.
- A section with no content is not rendered at all: no heading, count, or placeholder.
- A booking appears in at most one section. A booking in This week is not repeated in a later section, even if it also needs a response or is led by the member.
- "Today", "the next seven days", and the led-booking day are decided by each booking's own timezone, not the member's profile timezone.
- Every place that links to the member panel root (post-login redirect, invitation acceptance, email footers) lands on this dashboard. Nothing sends a member to the public welcome page.
- The dashboard is one combined view across all the member's teams, with the team named on each item. It has no team filter. Team filters belong on the full lists, such as news and bookings.
- When Inbox, This week, Needs your response, and Next booking you lead are all empty, a single "You're all caught up" message is shown. It is not dismissible and is always shown whenever it applies. Latest news is informational and does not count as something needing attention, so it can appear below that message.
- If every section is empty, the all-caught-up message is the only content and points to where team bookings can be found.
- A section that fails to load shows an error or is left out and logged. It is never presented as empty or as all caught up.
- Responding to a booking or marking an inbox item read updates the affected sections without a page reload, and a section disappears when its last item is removed.
- Each section shows only data the member may already see. Bookings, news, and inbox rows are scoped to the member's own teams and rows, and suspended or removed team memberships contribute nothing. Phone numbers, emails, and emergency details are not shown.
- Cancelled and past bookings never appear.
- Dates and times follow the display rules already used on the booking screens and omit seconds.
- The manage and admin panels are out of scope. Their attention needs differ, so each needs its own dashboard stories.

## Notes

- The news feed (`/news/{team:slug}`) and the bookings list (a Filament team filter) already have team filters.
- Teams manager attention and admin dashboard stories are deferred.