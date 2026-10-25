---
id: US-48
title: "Suspend and Restore a Member"
status: done
area: safety
scope: required
---

## Story

As a manager, I want to suspend a member during an investigation, or because required training or checks are not done, so they are locked out until the matter is resolved and can return without setting up their account again.

## Acceptance criteria

- A manager can suspend someone, and can restore them later.
- While suspended that person has no access and is sent nothing about bookings or other activity.
- A manager can still remove them from a booking or a team, and can view and edit their profile. Those actions stay silent toward the suspended person.
- Membership, profile, and attendance already recorded stay in place.
- On a roster, only a manager sees a suspended person.
- Suspending is separate from leaving a team.

## Decisions

- Suspension is system-wide: a suspended person has no access to any team or panel, including when already signed in.
- Any manager of a team the person belongs to, a system admin, or someone with oversight can suspend and restore. A team manager cannot suspend someone with oversight or system-admin rights.
- The action lives in a "More actions" menu on the member and user edit screens, not on the list or view screens. The view screen shows a "Suspended" badge.
- There is no reason field, because recording a reason could itself breach policy. The activity log records who suspended or restored and when.
- Nothing is sent to the suspended person, including on suspension or restoration.
- Suspending a manager is allowed. The confirmation warns when a team would have no active manager. The person remains a manager in the database, so restoring them puts the team back as it was. Bookings left without a lead instructor must be handled separately.
- Suspending someone unassigns them as lead instructor on upcoming bookings that are not cancelled. A confirmed booking returns to tentative, as when a lead resigns (US-10). Past and cancelled bookings keep their lead, and nobody is emailed. The confirmation states how many bookings are affected. The manager alert for a booking left without a lead is [US-10](US-10-lead-instructor-resignation.md).
- Restoring a cancelled booking also removes its lead instructor if that person is suspended.
- Invitation send, resend, and revoke stay hidden for a suspended placeholder, and sending is refused, so they are not emailed.
