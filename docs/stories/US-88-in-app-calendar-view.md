---
id: US-88
title: "In-app Calendar View"
status: done
area: bookings
scope: extra
---

## Story

As a team member, I want an in-app calendar of bookings and due tasks I can see, so that I can scan my schedule by month or week without leaving the site or relying only on ICS feeds.

## Acceptance criteria

- Member panel has `/calendar` and the Teams panel has `/teams/{tenant}/calendar`, both as Filament pages with a FullCalendar grid.
- Events include bookings (`start_at` / `end_at`) and tasks with a `due_at` (shown as all-day on the due date), using the same visibility rules as booking/task lists and ICS (`forUser` / `visibleTo`). System admin alone does not unlock every team’s schedule.
- Tabs are **Rota** (default) and **All**. There is no Invites, Upcoming, or History tab on the calendar.
- Event fill colour comes from the team brand colour. Tentative bookings use striped shading; cancelled and past bookings are muted. Completed or cancelled tasks are muted; incomplete tasks (including overdue) stay full opacity. Viewer attendance (and task assignee status) uses a leading icon on the event chrome, not a trailing title suffix.
- The calendar is read-only: click opens the existing booking or task view. No create, drag, or resize in this story.
- Mobile opens in a list month view; desktop opens in the month grid.
- The current view and date are kept in the URL (replaceState) so a reload or browser Back restores them.

## Progress

Implemented with `saade/filament-fullcalendar` ^5, shared `ScheduleCalendarWidget` / `ScheduleCalendarEvents`, and feature coverage in `CalendarPageTest`.
