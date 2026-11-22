---
id: US-93
title: "Task Commenting Threads"
status: done
area: audit
scope: required
---

## Story

As a team member or manager, I want to post comments on tasks so that we can discuss progress and logistics in the same place as the work item.

## Acceptance criteria

- Provides an interactive comment thread on task view pages, in a Comments panel under the task details. It is not part of Recent activity. Reuses the polymorphic comments model and the shared comments Livewire panel with bookings ([US-40](US-40-booking-commenting-threads.md)).
- Anyone who can progress/complete that task can post (manager; unassigned: any current member; assigned: assignee only). System admin alone cannot post. Signed-out people cannot comment. Completed and cancelled tasks stay closed for new comments; existing comments remain visible.
- Soft-deleted comments stay in the feed, ghosted, for the author and team managers. Other viewers never see them. The author or a manager can then remove them completely. Only a manager can restore. Authors can edit live comments they wrote. Comment rows show Manager and Assignee badges where they apply (not Lead).
- A comment email about a task sets `References` and `In-Reply-To` to that task's current revision via `TaskMailThread`, so it joins the latest invite or update. Comments are sent immediately when `tasks.comments` (and parent `tasks.mail`) allow.
- Mail and inbox links open the teams task view with a `comment` query. That row is shown first, with older comments hidden until **Show older comments**. Recipients are the assignee (skipped when declined unless they manage the team) and active managers; the author is skipped. Unassigned tasks notify managers only.

## Progress

Done. Shared `Comments` Livewire / Blade and shared `CommentMail` / `CommentNotification` cover bookings and tasks. Nested replies, activity-log comment events, inbound plain-text reply → comment, and legacy todo-comment import stay out of this slice.
