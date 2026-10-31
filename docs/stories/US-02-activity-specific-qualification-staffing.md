---
id: US-02
title: "Activity-Specific Qualification & Staffing Requirements"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want qualification requirements to say who may lead so that I can see who is qualified and only a qualified person can lead.

## Acceptance criteria

- Unqualified people may be invited, added, and may accept. A pairing with no qualification rows accepts everyone, including as lead.
- When the pairing has rows, people who meet them are obvious on the manage guest list, and only one of those people who has also accepted can be the lead.

## Progress

Each row is one way to qualify, and a blank field means any value. Covering uses the held permit group's compiled covers (Canoeing River B2 does not meet Open Inland B1). A higher category, permit type, level, or award covers a lower one. Permit restrictions are ignored when deciding who meets the pairing. The manage guest list shows a Qualified badge for people who meet a pairing that has rows. Confirm and change-lead only offer an accepted person who meets the pairing. The invite modal uses this same check. Qualified and lead checks use the booking end date in the booking timezone: a permit must still be valid through that day. The rows belong to an activity–audience pairing, as [US-66](US-66-audience-qualification-requirements.md) defines. Reading a permit's restrictions is [US-52](US-52-lead-instructor-session-roster.md).
