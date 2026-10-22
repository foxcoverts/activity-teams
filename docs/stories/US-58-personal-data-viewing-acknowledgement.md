---
id: US-58
title: "Personal Data Viewing Acknowledgement"
status: partial
area: safety
scope: required
---

## Story

As a team member or administrator, I must explicitly acknowledge that I will use another person's personal details strictly for team matters before viewing them.

## Acceptance criteria

- Before personal details about another identifiable person are displayed, the viewer must actively check an unchecked confirmation that they will use the information strictly for team matters.
- This requirement applies to every screen and workflow that displays another person's personal details, including profile and contact information, qualifications, booking or attendance details, and personal information in incident reports.
- A person can view and manage their own details without this acknowledgement.
- The acknowledgement is given per person and per protected section (for example Contact Details and Emergency Contact are acknowledged separately, as in the legacy app), not per screen and not across everyone. Acknowledging for one person does not reveal another person's details, and acknowledging one section does not reveal another.
- An acknowledgement lasts 30 minutes, after which the viewer must acknowledge again. A viewer can ask for a longer period later if this proves too short.
- The acknowledgement does not grant permission to view information. Existing role, team, booking, and time-window authorization rules still apply, and unauthorized users cannot reveal the information by checking the box.
- No protected details are displayed before the acknowledgement is made.
- The confirmation explains the permitted purpose in clear language, using a neutral form of the legacy notice: "You may only use these details to contact team members regarding legitimate team matters. Any other use of these contact details, no matter how well intended, will be in breach of UK data protection laws." The unchecked control reads "I have a legitimate reason to view these details".
- Each acknowledgement is stored in a dedicated table recording who acknowledged, for whom, and when. That table is both the audit trail and the source the application checks for a current (under 30 minutes old) acknowledgement. There is no screen for viewing these records yet; they exist so they can be looked up if needed.

## Refinement

Decided: 30-minute lifetime, per-person and per-section scope, a dedicated acknowledgement table as audit trail and validity check, neutral legacy wording, and a separate notice and unchecked control for each protected section (above).

Retention: acknowledgement records are kept for 6 months and then pruned by a scheduled task. They are deliberately not removed or anonymised when either person's account is deleted. Otherwise someone could view many people's personal details and then delete their own account to erase the evidence. To keep each record meaningful after an account is gone, it stores the names of the viewer and the person viewed at the time, and deleting a user must not cascade to or null these records. This is a documented operational reason to retain specific information under [US-57](US-57-member-account-privacy-controls.md); that story should reference it, and a deleted person's retained record should hold only what is needed to identify the viewing.

Still to confirm: which surfaces beyond the member and user screens (bookings and attendees, incident reports, lead contact windows) are covered first. This acknowledgement is an application safeguard, not by itself a determination of lawful basis or a guarantee of GDPR compliance.

## Progress

Done: the Contact Details and Emergency Contact sections on the team Member and global User view and edit screens (new users have nothing to acknowledge) require a per-person, per-section acknowledgement that lasts 30 minutes and is recorded in `personal_data_acknowledgements` (pruned after 6 months, kept when accounts are deleted). Protected values are not rendered or included in the page's Livewire state before acknowledgement, and self-view is exempt. Email, phone and emergency-contact details were removed from the Member and User listings.

Remaining: other surfaces that show another person's details are not yet covered: booking and attendee screens, qualifications, incident reports, and the lead contact windows in US-18/19.
