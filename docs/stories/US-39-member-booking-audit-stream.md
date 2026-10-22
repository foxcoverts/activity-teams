---
id: US-39
title: "Member Booking Transparency & Audit Stream"
status: done
area: audit
scope: required
---

## Story

As a team member, I want to view contextual activity logs and change histories on specific bookings so that I can understand when and why details or attendance states were updated.

## Acceptance criteria

- Displays a contextual recent activity history feed directly on individual booking detail views.
- Clearly records timeline events such as attendance status updates, description changes, or scheduling adjustments.

## Progress

The booking view, for a manager and for a member, shows a timeline of that booking. Attendance reads as a sentence that names who recorded it, the member, and the status. When someone records their own attendance, it reads as what they said, such as that they can help. Other changes name the field, such as the start, the notes, or the status, and a cancellation keeps its reason. The member timeline keeps those names as text. The manage timeline links each teammate it names, the person who made the change and the person it is about, to that team's member page, and it does not link into the site user directory. The team-wide manager feed remains [US-38](US-38-manager-activity-audit-feed.md).
