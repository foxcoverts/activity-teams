---
id: US-19
title: "On-The-Day Emergency & Contact Access (Participants)"
status: done
area: safety
scope: required
---

## Story

As a team member attending an event, I want the lead instructor's phone number so I can report running late, and their emergency contact if they are incapacitated, so someone can reach the right person.

## Acceptance criteria

- A person who is attending (accepted or tentative) can see the lead instructor's phone number until 23:59:59 on the booking end date. There is no earlier start.
- Anyone attending (accepted or tentative) can see the lead instructor's emergency contact name and phone from 00:00 on the booking start date through 23:59:59 on the booking end date.
- Nothing is shown for a cancelled booking, or when either person is suspended.
- Before viewing the details, the member must acknowledge their proper use as required by [US-58](US-58-personal-data-viewing-acknowledgement.md).
- Where a team manager is on the guest list as an attendee but is not the lead, attendees can also see their phone number on the same terms as the lead's phone.

## Progress

Dates are those of the booking's own timezone, not the viewer's. A system admin does not bypass these GDPR controls. Contact is offered from the guest list as a single action with separate US-58 acknowledgements for phone and emergency (see [US-18](US-18-lead-instructor-contact-access.md)).
