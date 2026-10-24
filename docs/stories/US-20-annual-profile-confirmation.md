---
id: US-20
title: "Annual Profile & Emergency Contact Data Confirmation"
status: done
area: safety
scope: required
---

## Story

As a team member, I want to be prompted annually to review and confirm my profile and emergency contact details so records remain accurate and meet GDPR compliance.

## Acceptance criteria

- Twelve months after the account is created (invitation accept or first-user register), and every twelve months after the last confirmation, the person must review and confirm contact and emergency details.
- When due, the first screen of the visit is a take-over. They cannot dismiss it by clicking outside or going back. The only ways off it are Confirm or an explicit Defer.
- The take-over is the edit-then-confirm step: only email, phone, emergency contact name and phone, with the same US-18/US-19 notices and a link to the privacy notice. Name, timezone, and section stay on the [US-56](US-56-member-profile.md) profile.
- They can confirm the values as shown, or change those fields and confirm in the same action. Changing email still follows verification. Saving those same fields on the US-56 profile also counts as confirmation.
- This does not lock the rest of the app the way unverified email does. After Defer, they can use the app, with a banner that returns to the same take-over.
- Defer lasts only for the current visit. A new sign-in, a new browser session, or the next calendar day in the person's timezone shows the take-over again.
- A system admin does not bypass this for their own account. Placeholders are out of scope.
- Profile screens display clear notices on how emergency data is accessed by lead instructors.

## Progress

Implemented: a full-page take-over twelve months after account creation or the last confirmation, covering email, phone, and emergency contact with the US-18/US-19 notices and a privacy-notice link. Confirm saves those fields (and counts a US-56 profile save of the same fields). Defer is session-scoped for the current calendar day in the person's timezone, after which a banner returns to the take-over. Unverified email still gates the panels first. System admins are not exempt. Placeholders are out of scope.

A system admin does not bypass these GDPR controls. There is no annual recertification in climbing-team; that is not a parity gap.
