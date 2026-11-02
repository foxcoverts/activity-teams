---
id: US-59
title: "Team Member Invitations"
status: done
area: membership
scope: required
---

## Story

As a team manager, I want to add people to my team by name and invite them by email, so that I can put them on bookings and record their attendance without being shown every user in the system.

## Acceptance criteria

- A team manager can add a named person to their own team as a parent, member, or manager, giving a name and an email address to invite. Managers never see a list of users outside their team.
- The named person exists immediately as a placeholder inside that team only. They can be added to bookings and have their attendance set by a manager, even before they accept the invitation.
- The invitation email is sent to the address given and contains a signed link that expires after 14 days. A manager can resend or revoke a pending invitation; a revoked or expired link cannot be used, and an expired or revoked link tells the invitee to ask their manager for a new invitation.
- An invitee can accept the invitation by creating a new account or by signing in to an existing account. The existing account's email address need not match the invited address. Accepting an invitation takes over the named person: the placeholder's team role, booking attendance and lead assignments move to the account, and the placeholder is removed.
- Before sending an invitation, the app checks whether the address belongs to someone already in the manager's team and, if so, refuses it, saying the person is already a member. This reduces, but cannot prevent, an existing member claiming a placeholder, because an invitee may sign in with a different account.
- If an account that is already in the team claims a placeholder, the memberships merge into the account's existing one and the higher role wins (manager over member over parent). Attendance and lead assignments move to the account on a best-effort basis. Where both the placeholder and the account already have a record on the same booking: identical responses are kept as they are; if one is needs-action and the other is not, the needs-action record is discarded and the other kept; if both have been answered differently, the account's attendance on that booking becomes needs-action so the member decides.
- If the invited email address belongs to a suspended account, the invitation is refused and the manager is told that the address belongs to a suspended account.
- Creating an account through an invitation sets the account's timezone, contact details and emergency contact details. The form explains how contact and emergency contact details are used and who can see them (see US-18, US-19 and US-58).
- Only accounts with a verified email address receive emails, except the invitation email itself. A person without a verified email address cannot be invited to a booking, but a manager can still record their attendance.
- An invitee who already has an account can decline. Declining leaves the placeholder in place, so the manager can still record their attendance, ends the invitation, and tells the manager the invitation was declined. The existing account is untouched, and the invited address is never written onto the placeholder.
- A manager is not told whether an invited address already has an account, except when it belongs to a suspended one.
- When creating a new account, the invitee can change the email address from the one invited, for example to use a different address for their account. The new address must be unique and must not belong to a suspended account (the invitee is not told why the latter is refused). If it is changed, the account's email is unverified until the invitee follows a verification email sent to it, so the person cannot be invited to bookings or emailed until then.
- A signed-in account with an unverified email address is shown a persistent prompt to verify it and can resend the verification email. Both panels already enable Filament's email verification, which gates access to the panels until the address is verified; this story must keep that gate and not bypass it for accounts created through an invitation with a changed address. A person may still verify later through the link in the emailed message.
- Accepting an invitation verifies the invited email address only if the account is created with it; signing in to an existing account does not change that account's verified status.
- Pending invitations are managed in the team's Members list, not a separate area. Each placeholder has at most one invitation; resending replaces the previous link, which stops working.
- The Members list shows an invitation status (Active, Invited, Invitation expired, Declined, Not invited; Not invited covers revoked or never sent) and can be filtered to people who have not accepted, including those who declined. An Add member action creates the placeholder, membership and invitation together. Placeholder rows offer Edit, Send or resend invitation, Revoke invitation and Remove from team; active accounts keep their existing actions and get no invitation actions.
- A manager can invite a person who previously declined. The app warns that they declined, and does not block it, because the invitation may have gone to a mistyped address. When sending or resending, the manager can correct the invited email address. The earlier decline stays in the activity log.
- The invited email address is shown only on the placeholder's own screen, never in the Members list (see US-58).
- Adding, resending, revoking and accepting invitations are recorded in the activity log (US-47 team isolation applies to all of it).

## Refinement

- Placeholder model: a `User` row with no password and no email address. The invited address lives only on the invitation record, so it never collides with the unique `users.email` of an existing account. Placeholders are ordinary rows in the global Users list so oversight and system admins can review, block or diagnose them; they are not hidden.
- A user with no email address must also have no password, so a placeholder can never sign in. Enforce this in the database with a check constraint (`email IS NOT NULL OR password IS NULL`) as well as in the model. `password` is already nullable; only `users.email` needs to become nullable, and it stays unique when present.
- The under-18 safeguarding flag is set by the manager, and the team role is chosen for that team; neither is chosen by the invitee.
- Legacy created a global `User` with an empty password and re-sent a signed setup link while the account was inactive. It had no claim-into-existing-account path and no suspended-email check.
- Suspended-email refusal is deliberately explicit: a generic error would lead a manager to ask the person for another email address, working around the suspension without knowing it exists. The message names the reason but reveals nothing else about the account.
- Depends on US-47 (team isolation), US-48 (suspension), [US-56](US-56-member-profile.md) (profile fields) and US-58 (acknowledgement).

## Progress

Implemented: placeholder people (nullable `users.email` with a database check constraint and model rule), one invitation per placeholder, Add member and Send/Resend/Invite again/Revoke actions on the Members list with status and a Not yet accepted filter, member actions on both the list and view page, system-admin invitation access, the signed 14-day invitation page (create account with editable email, accept with an existing account, sign out to create another account, decline), panel-scoped email verification for accounts created with a changed address, verified active manager notifications when an invitation is declined, merge rules for manager, member, and parent roles plus attendance, lead assignments, qualifications and key rings, suspended-email and already-in-team checks, attendance recording for people without a verified email, and activity-log entries.

An expired or revoked link tells the invitee to ask their manager for a new invitation; there is no self-service request. A candidate for [US-55](US-55-inbound-email-routing-and-alerts.md) refinement: forward replies to the invitation email to the team's managers, so invitees can reply to contact their manager.
