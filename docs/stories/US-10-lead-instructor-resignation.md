---
id: US-10
title: "Lead Instructor Availability Change & Resignation Workflow"
status: partial
area: bookings
---

## Story

As an Activity Manager, I want the system to automatically handle when a lead instructor changes their availability by reverting the booking to Tentative and alerting me so that shifts are never left unverified.

## Acceptance criteria

- Intercepts declined attendance responses from leads.
- Automatically reverts booking state to Tentative, unassigns the instructor, and triggers manager alerts.
- An alert about one booking may set `References` to that booking's current revision. The subject can stay distinct from the invite and update subjects.

## Progress

A lead who answers maybe or no is unassigned. A confirmed booking returns to tentative. A comment alone changes neither. The manager alert, with `References` for that booking's current revision and a subject of its own, is still open.
