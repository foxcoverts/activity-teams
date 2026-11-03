---
id: US-74
title: "Dashboard: Latest News"
status: done
area: communications
scope: extra
---

## Story

As a team member, I want to see the latest team news on my dashboard, so that I notice announcements without having to open the news feed.

## Acceptance criteria

- Shows the single most recent published post from the combined [US-54](US-54-team-news-posts.md) feed for the teams the member belongs to.
- A post older than 30 days (by its single publication date) is hidden, so the card disappears for quiet teams rather than showing a stale announcement.
- The card matches an item in the news feed: it shows the title, team, author, publication date, and a short excerpt, and links to the post. A link to the full news feed is also shown. Opening the post, from this card or anywhere else, also marks the member's matching unread inbox notice read, using the same read stamps as the inbox.
- A "New" marker is shown only when the member has an unread inbox notice for that post. News has no read tracking of its own, and no new tracking is added.
- A post that is also in the member's inbox still appears here. Duplication is acceptable.
- Drafts, scheduled, and unpublished posts never appear. A post that is later unpublished disappears from the card.
- News from teams the member does not belong to never appears.
- The member's opt-out of direct news distribution does not hide this card, as the feed stays available to them.
- News is informational. It appears when a published post exists within the age limit, and its presence alone does not suppress the all-caught-up message in [US-69](US-69-member-dashboard.md).
- The section is hidden when there is no qualifying post.

## Notes

- This story is for team members only. Any manager dashboard card is a separate decision.