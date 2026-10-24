---
id: US-56
title: "Member Profile"
status: done
area: members
scope: required
---

## Story

As a signed-in person, I want to maintain my own name, contact details, emergency contact, and timezone, so that people who need them for a booking can reach me and my records stay current.

## Acceptance criteria

- An authenticated account holder can update their own name, email, phone, emergency contact name and phone, and timezone. This includes team members and team-less oversight / system admins ([US-60](US-60-oversight-system-admin-invitations.md)).
- Changing email follows the account's verification requirements (Filament already gates panels until verified; keep that).
- Phone and emergency-contact fields use the same usage notices as the invitation accept form.
- The profile shows a link to the privacy notice (same URL as account creation).
- Section stays manager-set ([US-59](US-59-team-member-invitations.md)). Password, export, and deletion stay [US-57](US-57-member-account-privacy-controls.md).
- Managers keep the existing ability to edit another person's profile ([US-48](US-48-suspend-and-restore-a-member.md)); self-view stays exempt from [US-58](US-58-personal-data-viewing-acknowledgement.md).

## Refinement

Fields already exist on `User`. The member panel hosts the custom profile (name, email, phone, emergency contact, and timezone); the manage user menu opens that same page. Booking-mail preferences moved to [US-61](US-61-booking-notification-settings.md). Annual confirmation of contact details is [US-20](US-20-annual-profile-confirmation.md).

## Progress

Implemented: custom Filament profile on the member panel for name, email (with verification hint and re-verification on change), phone, emergency contact, timezone, the invitation usage notices, and a link to the privacy notice. The manage user menu links to that same member-panel page. Phone fields use E.164 storage. Team-less system admins can open the same profile. Section stays manager-set. Password, export, and deletion are on [US-57](US-57-member-account-privacy-controls.md).
