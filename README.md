# Activity Teams

[![Test](https://github.com/foxcoverts/activity-teams/actions/workflows/test.yml/badge.svg)](https://github.com/foxcoverts/activity-teams/actions/workflows/test.yml)

Management site for Fox Coverts activity teams. Live at [climbfoxcoverts.co.uk](https://climbfoxcoverts.co.uk/).

## Capabilities

- **Bookings and attendance** — schedule sessions against catalog activities and venues, invite and manage rosters, lead instructor workflows, rollcall, debriefs, related multi-day bookings, manager triage for tentative sessions, and an in-app calendar (`/calendar` and per-team calendar) alongside ICS feeds.
- **Qualifications and staffing** — permits and qualifications, expiry tracking, and activity staffing rules.
- **Venues and keys** — venue catalog with addresses and map pins, booking logistics, and key rings / pass-a-key.
- **Communications** — email invites and RSVPs, ICS subscription feeds, public booking summaries, inbound calendar replies, news posts, notification preferences, and a member inbox.
- **People and privacy** — member profiles, team and oversight invitations, suspend/restore, and acknowledged access to personal data.
- **Operations** — tasks, incident reporting, experience logbook, bounce triage, and system administration across multiple teams.

Three Filament panels: **Members** (`/`), **Teams** (`/teams`), and **Admin** (`/admin`).

## Stack

Laravel 13, Filament 5, Spatie activity log; local development via Laravel Sail (PHP 8.5, MariaDB).

## Next steps

- [INSTALL.md](INSTALL.md) — local setup
- [docs/](docs/README.md) — product notes, user stories, and development detail
