---
id: US-02
title: "Activity-Specific Qualification & Staffing Requirements"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want different activities to carry their own qualification requirements so that I can see who is qualified and only a qualified person can lead.

## Acceptance criteria

- Unqualified people may be invited, added, and may accept. An activity with no qualification rows accepts everyone, including as lead.
- When the activity has rows, people who meet them are obvious on the manage guest list, and only one of those people who has also accepted can be the lead.

## Progress

A system admin sets the activity's acceptable qualifications. Each row is one way to qualify, and a blank field means any value. A higher category, permit type, level, or award covers a lower one. Permit restrictions are ignored when deciding who meets the activity. The manage guest list shows a Qualified badge for people who meet an activity that has rows. Confirm and change-lead only offer an accepted person who meets the activity. The invite modal uses this same check. Reading a permit's restrictions is [US-52](US-52-lead-instructor-session-roster.md).
