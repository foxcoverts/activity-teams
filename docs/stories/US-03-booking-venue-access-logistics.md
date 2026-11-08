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
- Clicking Keys opens a person-first roster of holders for the required locks, grouped by whether each person is attending, invited, not invited, or unable to attend, with cupboard rings listed separately.
- Cupboard rings are labelled as needing passing to an attendee. Other access arrangements stay manual: there is no warden workflow, notify action, or pass-a-ring action.

## Progress

The Keys header action is the indicator. It is hidden when the booking needs no keys. A covering key such as the warden key counts if that person has accepted. Cupboard and safe rings do not. Tentative, declined, and awaiting responses do not make the action green. Recording attendance on the guest list updates the action without reloading the page.

The modal lists people (and cupboard rings) by situation, with chips for the rings and locks they cover. Team managers can Contact a holder on the guest list, Invite someone not yet invited, Edit attendance for someone already on the guest list, and open View member when they can view members. A lead who is not a manager can Contact only. Phone numbers and emails are not shown in the roster itself; Contact uses the same booking Contact modal and [US-58](US-58-personal-data-viewing-acknowledgement.md) gates as the guest list. Pass-a-ring remains [US-51](US-51-pass-a-key.md).
