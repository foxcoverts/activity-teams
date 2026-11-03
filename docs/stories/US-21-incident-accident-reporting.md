---
id: US-21
title: "Incident & Accident Reporting & Compliance"
status: done
area: safety
scope: required
---

## Story

As a team member or activity leader, I want to submit an incident or accident report so the responsible team's managers receive it and I receive a copy.

## Acceptance criteria

- Provides a structured report for incident date and time, location name and description, people involved, response, damage, account of the incident, and reporter details.
- Defaults the incident date and time to the current application date and time.
- Requires the reporter to select a team they currently belong to. If they belong to exactly one team, it is selected by default; otherwise, they choose the responsible team.
- Offers a lookup of the selected team's catalog venues. If that team has exactly one venue, it is selected by default; choosing a venue copies its name and address into the editable place-of-incident field without linking the report to the Venue record. Location details stay for on-site detail about where the incident happened.
- Preserves a manually selected venue or edited location details when the team changes. An untouched, automatically selected venue default is replaced by the new team's sole venue, or cleared when the new team has multiple or no venues.
- Queues one email per report: the selected team's active managers are recipients, and the reporter is copied and set as the reply-to address.
- Includes the selected team name and incident details in the email. Injury reporting includes the RIDDOR warning when the hospital or injury criteria apply.
- Retries transient delivery failures for approximately 48 hours. After retries are exhausted, the job is retained in `failed_jobs` and active, verified system administrators receive a failure alert with instructions to retry it.
- The application does not persist report content or provide an in-app report viewer, list, or editing workflow. Email is the report distribution and record path; the queue and `failed_jobs` are temporary delivery storage.
- The reporter and selected team's active managers are the only application-selected email recipients. US-58 in-app acknowledgement does not apply because the application does not render incident-report personal details.

## Progress

The member wizard, current date/time and team-venue defaults (venue name and address in place of incident; on-site detail in location details), team-scoped recipients, reporter copy, long-lived queue retries with critical logging, and system-administrator failure alert are implemented and covered by focused feature tests. Report content is not stored in the application, so post-submission editing and application retention are not part of this workflow.
