---
id: US-03
title: "Booking Venue Access & Logistics Planning"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want each booking to show clearly whether a useful key holder is attending so that I can arrange any other access by hand before the session.

## Acceptance criteria

- When a booking needs keys, the Keys action on the booking view is green if at least one accepted attendee holds a required key (or a key that covers one), and amber if nobody attending does.
- Clicking Keys opens the required keys for that activity at that venue, grouped by whether each ring is held by someone attending, invited, in the cupboard, not invited, or unable to attend.
- Cupboard rings are labelled as needing passing to an attendee. Other access arrangements stay manual: there is no warden workflow, notify action, or pass-a-ring action.

## Progress

The Keys header action is the indicator. It is hidden when the booking needs no keys. A covering key such as the warden key counts if that person has accepted. Cupboard and safe rings do not. Tentative, declined, and awaiting responses do not make the action green. Recording attendance on the guest list updates the action without reloading the page.

The modal lists each required key and the rings that open it. On the manage panel, a current team member's name links to their member page so the manager can open contact details there after the [US-58](US-58-personal-data-viewing-acknowledgement.md) acknowledgement. Phone numbers and emails are not shown in the modal. Pass-a-ring remains [US-51](US-51-pass-a-key.md).
