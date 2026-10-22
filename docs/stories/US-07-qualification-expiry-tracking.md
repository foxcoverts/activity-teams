---
id: US-07
title: "Qualification Expiry Tracking"
status: not-started
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want the system to track permit and qualification expiration dates for team members so that I can easily identify when safety credentials are approaching expiry or have lapsed.

## Acceptance criteria

- Logs expiration timelines for time-bound qualifications (such as Scout permits).
- Displays clear status indicators or warnings on member profiles when qualifications are nearing or past their expiry date.

## Progress

The expiry date is stored for every type except Mountain Training, which does not expire. Warnings are not built. When they are, a current qualification is green, one that expires within three months is amber, and an expired one is red.
