---
id: US-56
title: "Member Profile & Notification Preferences"
status: not-started
area: members
scope: required
---

## Story

As a team member, I want to maintain my own profile and choose which booking emails I receive globally or for a specific booking, so that my contact details stay current and I can control booking-related email.

## Acceptance criteria

- A member can update their own name, email address, phone number, emergency contact name and phone number, and timezone.
- Changing an email address follows the account's email-verification requirements.
- A member can configure global defaults for `comment_mail`, `invite_mail`, `change_mail`, `confirm_mail`, and `cancel_mail`.
- A member can override each of those settings for an individual booking. An unset booking-level value inherits the global setting, and an unset global value uses the system default.
- `comment_mail` supports `all`, `reply`, `leader`, and `none`; the other settings can be enabled or disabled.
- Notification preference records are private to the member and cannot be changed by another member.
- These preferences apply to booking-related emails. Whether they also control team news distribution is a separate decision for US-54.

## Refinement

Password changes, personal-data access/export, and account deletion are covered by [US-57 Member Account & Privacy Controls](US-57-member-account-privacy-controls.md).
