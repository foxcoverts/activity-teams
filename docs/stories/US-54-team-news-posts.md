---
id: US-54
title: "Team News Posts & Distribution"
status: done
area: communications
scope: required
---

## Story

As a team manager, I want to publish news for my team and have members see a combined feed and receive it directly, so that they can stay up to date even when they do not regularly visit the system.

## Acceptance criteria

- A manager can create and publish news for a team they manage, and edit or unpublish that team's posts.
- A member can view one combined feed of published news for the teams they belong to.
- Members cannot see news belonging to teams they do not belong to.
- Publishing a post emails the relevant team members who have not opted out of direct news distribution; it is not available only in the feed.
- Members can opt out of direct news distribution without losing access to the combined feed.
- Refinement defines how subsequent post edits are handled and how delivery failures are surfaced.

## Progress

Done. Combined article feed at `/news` (not a table); team slugs; Atom/RSS at `/feeds/{feed_token}/`; `rel="alternate"` discovery on signed-in panel pages; deferrable inbox + due job + digest; Publish XOR Republish; past dates stamp `notified_at`; scheduled Unpublish to draft.

News is not high urgency. `notify()` writes the inbox (`RecordsInbox`, database channel only). The digest is the email. Off `news.mail` still writes an inbox row; that row is not emailable. A scheduled job notifies posts whose `published_at` is in the past and `notified_at` is null. Saving a past date stamps `notified_at` to that `published_at`. Publish and Republish are never both visible. Unpublish returns a live or scheduled post to draft and does not clear `notified_at`. Edit of a live post updates the feed only. Delivery failures stay on the queue / failed-job path.

Use [US-61](US-61-notification-settings.md) catalog channel `news.mail` (`hasSubject: false`, `listUnsubscribe: true`). Gate digest sends with `MailPreference::allows`. Include the preferences footer and RFC 8058 `List-Unsubscribe` (POST mutes `news.mail`; GET must not write). Show the news control on the existing Notifications page. Feed access stays for opted-out members.
