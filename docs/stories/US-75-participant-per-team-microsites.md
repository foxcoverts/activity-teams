---
id: US-75
title: Participant Per-Team Microsites & Team-Hopping Navigation
status: done
area: Core Qualifications & Team Management
scope: essential
---

# US-75: Participant Per-Team Microsites & Team-Hopping Navigation

## Description

As a volunteer participant who belongs to multiple activity teams, I want a dedicated team-scoped area where I can view team-specific leadership contacts, documents, and news, and easily switch between my different teams without being forced into a manager control panel.

## Acceptance Criteria

1. **Dedicated Panel Structure:** Participants have a dedicated entry point or panel (e.g., `/teams/{team}`) separate from the untenanted Members workspace and the global Admin panel. Managers use the same Teams panel as members, with management capabilities added.
2. **Fluid Team Hopping:** A persistent tenant/team switcher is available within the team panel to allow quick navigation between different teams the user belongs to.
3. **Resource Scoping:** Each team page securely scopes its resources (such as local documents, news posts, and filtered bookings) strictly to the selected active team.
4. **Global Continuity:** Global account elements (such as personal profile, universal inbox, and cross-team qualifications) remain accessible outside of individual team scopes, preserving a clear separation between personal records and group-specific spaces.

## Progress

- Met: the teams panel (`/teams/{team}`) shows a dashboard, bookings, documents, news, tasks (when the feature is on) and a Leaders page, each scoped to the active team (criteria 1 and 3). The member panel lists bookings, news and assigned tasks and links through to the teams panel. Managers share this Teams booking experience and add create/edit/status and guest-list management there.
- Met: the tenant switcher keeps the current team when moving between pages and resources (criterion 2). When a matching page is accessible on the other team, the switcher stays on that resource’s index (or Dashboard / Manager / Leaders); record URLs are not preserved across tenants.
- Met: Inbox, Logbook, My keys, Qualifications and Report an incident are reachable from the Teams panel under **Personal**. **Home** (member dashboard) sits ungrouped near the top as the attention exit. **My keys** is the member-panel rings list; manager **Keys** sits in **Manage** (above Setup) with Manager triage first, then Keys, Members, and Reports. System Admin sits in its own **Admin** group below Setup. Profile / notifications / feeds remain in the user menu (criterion 4). The member panel is mainly an attention landing before hopping into a team.
- Met: Member nav labels **Home** for the member dashboard (and **My keys** in Personal); Teams nav labels **Dashboard** for the team overview so the top item no longer leaves the team. The Member/Admin **Teams** exit deep-links to a matching teams resource when one exists.
- Met: the Leaders page lists the team's active managers as cards (avatar and name, two columns on large screens, alphabetical). A Contact action shows email and phone only after the US-58 acknowledgement.
