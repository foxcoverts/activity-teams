---
id: US-07
title: "Qualification Expiry Tracking"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want the system to track permit and qualification expiration dates for team members so that I can easily identify when safety credentials are approaching expiry or have lapsed.

## Acceptance criteria

- Logs expiration timelines for time-bound qualifications (such as Scout permits).
- Displays clear status indicators or warnings on member profiles when qualifications are nearing or past their expiry date.

## Progress

The expiry date is stored for every type except Mountain Training, which does not expire. Member and manager qualification lists, and the qualification view, colour the date: green when more than three months away, amber within three months, red after the end of that day. Mountain Training shows no date and no colour. Booking staffing uses the session end date separately; profile colours stay as of today.
