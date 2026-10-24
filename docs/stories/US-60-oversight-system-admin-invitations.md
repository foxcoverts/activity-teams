---
id: US-60
title: "Oversight & System Admin Invitations"
status: done
area: membership
scope: required
---

## Story

As a system admin, I want to invite someone as oversight and/or system admin by name and email, so they can take up that access without first joining a team.

## Acceptance criteria

- Only a system admin can send, resend, or revoke these invitations. Oversight and team managers cannot. Existing Access toggles on an existing account stay as they are.
- The inviter chooses oversight, system admin, or both (at least one required), giving a name and an email address to invite. Section defaults to Adult.
- The named person exists immediately as a placeholder with those flags and no team membership. They appear in the global Users list with an invitation status. The invited email address is shown only on that person's own screen, never in the Users list (see [US-58](US-58-personal-data-viewing-acknowledgement.md)).
- The invitation email is sent to the address given and contains a signed link that expires after 14 days. A system admin can resend or revoke a pending invitation; a revoked or expired link cannot be used, and an expired or revoked link tells the invitee to ask a system administrator for a new invitation.
- An invitee can accept by creating a new account or by signing in to an existing account. The existing account's email address need not match the invited address. Accepting does not grant a team. Claiming an existing account adds the invited flags to that account (existing flags and team memberships are kept) and removes the placeholder.
- Creating an account through an invitation sets the account's timezone, contact details and emergency contact details. The form explains how contact and emergency contact details are used and who can see them (see [US-18](US-18-lead-instructor-contact-access.md), [US-19](US-19-participant-lead-contact-access.md) and [US-58](US-58-personal-data-viewing-acknowledgement.md)). The invitee can change the email address from the one invited; if they do, it is unverified until they follow the verification email.
- If the invited email address belongs to a suspended account, the invitation is refused and the system admin is told that the address belongs to a suspended account.
- If the invited email address already belongs to a non-placeholder account, the invitation is refused and the system admin is told that the person already has an account, so they should grant access on that account instead.
- An invitee who already has an account can decline. Declining leaves the placeholder in place, ends the invitation, and emails all current unsuspended, active, verified system admins. Team managers and oversight-only users are not notified. The existing account is untouched, and the invited address is never written onto the placeholder. Team invitations keep emailing that team's managers.
- Invitation copy does not say the person has been invited to join a team, and does not tell them to ask a team manager.
- Pending invitations of this kind are managed on the Users list, not Members. Each placeholder has at most one invitation; resending replaces the previous link, which stops working. A system admin can invite a person who previously declined; the app warns that they declined and does not block it.
- After the first user, every new person is invited (a team invitation or this site-access invitation). The only remaining create-account path without an invitation is empty-database registration, which already makes that person a system admin. There is no Create User path and no setup-account email.
- Adding, resending, revoking and accepting invitations are recorded in the activity log.

## Refinement

- A site-access invitation is a `team_invitations` row with a null `team_id`. Intended access lives on the placeholder (`oversight` and `is_system_admin`), as a team role lives on membership.
- Placeholder model is unchanged from [US-59](US-59-team-member-invitations.md): a `User` with no password and no email address. The invited address lives only on the invitation record.
- Telling a system admin that an email already has an account is deliberate: they have the Users directory, unlike a team manager who must not be shown people outside their team.
- Depends on [US-49](US-49-system-administration.md), [US-58](US-58-personal-data-viewing-acknowledgement.md) and [US-59](US-59-team-member-invitations.md).

## Progress

Implemented: nullable `team_invitations.team_id` for site-access invitations, Add person and Send/Resend/Invite again/Revoke on the Users list (system admin only), placeholders with oversight and/or system-admin flags and no team, the same 14-day signed invitation page with site-access copy, decline notifications to active verified system admins, claim that ORs flags onto an existing account without granting a team, suspended-email and already-has-an-account checks, removal of Create User and the setup-account path, and activity-log entries. Empty-database registration remains the bootstrap that creates the first system admin.

A candidate for [US-55](US-55-inbound-email-routing-and-alerts.md) refinement: forward replies to a site-access invitation to the system admins.
