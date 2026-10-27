---
id: US-62
title: "Unmapped Calendar Reply Identity"
status: not-started
area: communications
scope: extra
---

## Story

As someone who replies to a booking invitation from a calendar address the system does not know, I want a way to confirm who I am or to ask the team's managers about joining, so a genuine reply is not lost and an unknown person cannot create an account themselves.

## Acceptance criteria

- An inbound iMIP `REPLY` whose attendee or from address does not match a known account is not applied to another person, and does not create an account or add someone to the booking.
- That message is not silently discarded. Until a person is associated, it is retained for review under [US-55](US-55-inbound-email-routing-and-alerts.md).
- If the respondent already has an account, they can confirm who they are (the issue's sketch is to ask them to sign in). Once associated, the reply's attendance is applied with the iMIP provenance in [US-15](US-15-inbound-calendar-response-parsing.md), and later `REPLY`s from that address can be applied as that person without asking again.
- If the respondent does not have an account, they are not offered a self-serve register or create-account step. The invitation may have been forwarded; they may want to join as a new member or as a guest. They are given a way to request access or get in touch with that team's manager(s). Adding them to the team or booking stays a manager action ([US-59](US-59-team-member-invitations.md), [US-50](US-50-invite-non-members.md)).
- Account creation stays invitation-only ([US-57](US-57-member-account-privacy-controls.md)). Matching gmail/googlemail or similar interchangeable domains for an existing account email remains US-15, not this story.
- Extra addresses stored for this purpose are personal data: they are included in US-57 export and deletion, and are gated by [US-58](US-58-personal-data-viewing-acknowledgement.md) when shown to anyone other than the account holder.

## Refinement

The confirmation mechanism, the no-account contact path, and where the extra address is stored are not decided. Candidates from [climbing-team#14](https://github.com/foxcoverts/climbing-team/issues/14) and this follow-up, to evaluate rather than assume:

- Email the sender and ask them to sign in to confirm who they are.
- If they have no account, offer request-access or contact-the-managers instead of a login dead end ([US-12](US-12-public-booking-summary.md) currently stops non-members at login).
- Record the address on that booking's attendance and use it for later mail about that booking.
- Record additional email address(es) on the person so later replies from those addresses match.

Do not treat those bullets as acceptance criteria until the product owner chooses among them (or a mix). Decide then:

- Whether association is self-service for existing accounts, manager-assisted, or both.
- Whether an unconfirmed reply should notify the sender, the booking's managers, or only sit in the US-55 queue.
- How a forwarded-invite respondent asks to join as a member versus as a guest, and how that request reaches the right team's managers without creating a user.
- Whether "guest" here means US-50 (invite onto the booking without team membership) or a different guest concept; the old global guest role is not this story.
