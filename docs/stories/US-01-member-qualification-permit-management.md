---
id: US-01
title: "Member Qualification & Permit Management"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want to record and maintain team members' qualifications (Scout permits, Mountain Training awards, Girlguiding awards, NSRA awards, and Archery GB awards) so that I can easily verify authorisation to supervise specific activities.

## Acceptance criteria

- Supports qualification types with distinct tracking rules. Scout permits and Girlguiding awards expire. Girlguiding is a closed set: a manager can record an award someone already holds, and once it has expired no replacement is issued. Mountain Training is the only type that does not expire.
- Managers can create, update, or remove qualifications for active members.

## Progress

A manager of the current team can add, edit, and remove qualifications for that team's current members. The records stay on the person, so a manager of another current team edits the same rows. Oversight can read them on Admin Users and cannot change them. The member can read their own list from Qualifications in the team menu. Changes are logged.

The Scout, Mountain Training, Girlguiding, NSRA, and Archery GB catalogues are YAML: 41 Scout permit groups with categories scoped to the group (including Archery with/without Compound Bows and climbing Multi Pitch). Linked selects only offer the chosen group's categories and allowed permit types. Stored records are a kind plus a JSON payload of catalogue names. NSRA forms use the full association name; summaries use NSRA. Archery GB summaries use the short name.
