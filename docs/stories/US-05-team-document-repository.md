---
id: US-05
title: "Team Document & Resource Repository"
status: done
area: qualifications
scope: required
---

## Story

As an Activity Manager, I want to upload and share internal compliance documents (such as risk assessments) so that all team members have centralised access to safety guidelines.

## Acceptance criteria

- A manager can upload, edit, and delete PDF documents with optional notes for a team they manage.
- A member can view and download documents for the teams they belong to, in one combined list with an optional team filter rather than a tenant switch.
- Members cannot see documents belonging to teams they do not belong to.
- Members do not see who uploaded a document. Managers see the uploader name on Teams.

## Progress

Done. Team-owned PDFs (1 KB–10 MB) with title and optional markdown notes. Teams list, edit, trash, restore, and force-delete; private disk under `documents/{team_id}/`. The file comes first on the form: choosing it fills title and a sanitised download filename, which the manager can edit. Filename stays on the edit form; the uploader name is on the Teams table. Members combined table at `/documents` with a team filter, notes under the title, and download from the list. No view pages. Lists sort by title by default; title and updated date are sortable. No site/campsite library and no category.
