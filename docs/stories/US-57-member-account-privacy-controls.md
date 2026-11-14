---
id: US-57
title: "Member Account & Privacy Controls"
status: done
area: members
scope: required
---

## Story

As an invited team member, I want to manage my account security and exercise control over my personal data, so that I can keep my account secure and leave the service with confidence.

## Acceptance criteria

- Account creation remains invitation-only; this story does not add public registration.
- Password change is a separate action (current + new + confirm), not on the profile. First-time password stays on invitation create-account and first-user Register. Password-reset already exists on both panels. Managers, oversight, and system admins cannot set another person's password.
- Creating an account (invitation create-account, and first-user Register) requires an explicit acknowledgement of the controller's privacy notice, with a link to the configured URL. Claiming an existing account shows the link but does not block on a new tick. This is the data subject's notice (Art 13), not [US-58](US-58-personal-data-viewing-acknowledgement.md). Store `privacy_notice_accepted_at` on create. The notice must describe this story's retention (named shell kept for legal claims; contact details removed after 30 days).
- A member can download a JSON copy of the personal data held about them (profile, memberships, qualifications, booking attendance, key rings). Usable and commonly readable; no other person's contact or emergency details.
- Self-delete is confirmed with the current password. The confirm UI states what is kept (name, qualifications, past attendance / lead history, key assignment) and what is removed (contact details, credentials, tokens, preferences, and other non-operational data), immediately vs after 30 days. They cannot sign in after confirm.
- Soft-delete (`deleted_at`) immediately. Do not hard-delete the row (attendance and qualifications must remain).
- After 30 days, a scheduled command strips remaining unnecessary personal data, leaving a shell: id, name, under-18 safeguarding flag, team role, qualifications, attendance, lead history, and key holder. Clear email, phone, emergency, password, remember token, ical token, timezone, access flags, US-20 confirmation timestamp, and any later preference records.
- Do not auto-expire the shell. There is no participant age on bookings (and no member date of birth), so a 3-year / age-21 cutoff cannot be applied safely.
- Refuse deletion if they are the last remaining system admin.
- If they are the last active manager of any team, do not refuse; email current system admins.
- If they hold key rings, leave the rings assigned; email the relevant team managers. Members must not see deleted people; managers may still see the named shell (including as key holder).
- Existing oversight Delete on Users (including placeholders) uses this same soft-delete path. Invitation claim/merge still force-deletes the leftover placeholder so that person does not become a ghost.
- `deleted_at` is enough to tell placeholders from deleted people. `isPlaceholder()` is a non-trashed user with no email.
- Managers still see deleted people (including deleted placeholders). The Status badge becomes Deleted, with a ghost as the badge icon (not an emoji in the name). Members never see those rows.
- A deleted person is a named shell: viewable, not editable. Managers cannot change their details, team memberships, qualifications, or key assignment (existing rings stay assigned). A system admin may restore within the 30-day window.
- No self-restore. A system admin may restore within the 30-day window (email still present). After purge, login cannot be restored; a new invitation would be a new account.
- Inform them on the confirm screen, then a signed-out confirmation page. Do not send a “your account was deleted” email.

## Refinement

The privacy notice is the markdown at `resources/markdown/privacy-policy.md`, served publicly at `/privacy`. Account-creation and profile links use that page unless `PRIVACY_NOTICE_URL` is set.

Personal-injury limitation is typically 3 years, or until 21 if the claimant was a minor. That mainly covers participants on sessions the member led. Bookings do not record participant ages, and a younger person might still have taken part, so there is no safe date to drop the named record. Keep the named attendance / lead shell indefinitely; contact details are not needed for that.

Depends on [US-59](US-59-team-member-invitations.md) and [US-60](US-60-oversight-system-admin-invitations.md) for invitation-only creation.

## Progress

Implemented: invitation-only creation with privacy-notice acknowledgement on invitation create-account and first-user Register (claim shows the link only); a separate Change password page on the member panel, linked from both user menus; JSON personal-data export; password-confirmed self-delete and oversight delete through the same soft-delete path; last-system-admin refusal; last-manager and key-holder alerts; a Deleted badge (Lucide ghost) on manager lists; deleted people kept as a read-only named shell (view and 30-day restore only); `users:purge-deleted` after 30 days leaving a named shell; and restore by a system admin only while the email is still present. Invitation claim still force-deletes the leftover placeholder. No deletion email is sent to the person who confirmed the action.

The shell is kept indefinitely because bookings have no participant age (and members have no date of birth), so a 3-year / age-21 cutoff cannot be applied safely. [US-58](US-58-personal-data-viewing-acknowledgement.md) acknowledgement records are kept after deletion, as that story requires.

The live privacy notice is the in-app `/privacy` page, edited as markdown. Solicitor review of lawful bases remains with the product owner.
