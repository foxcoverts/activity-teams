# Activity Teams

Management site for Fox Coverts activity teams: bookings, attendance, venues, and keys.

This is a **new** Laravel + Filament app. [`foxcoverts/climbing-team`](https://github.com/foxcoverts/climbing-team) remains the live system until this project is ready to replace it. Domain ideas and some logic are taken from climbing-team; the UI is Filament, not a port of the old Blade layouts. [PR 303](https://github.com/foxcoverts/climbing-team/pull/303) is a lesson in not migrating that UI in place.

## Stack

- Laravel 13, PHP 8.3+, Filament 5
- `spatie/laravel-activitylog` for audits
- SQLite by default (Sail/MariaDB optional)

## Run locally

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Seeded login: `leader@example.com` / `password`.

- Members panel: `/`
- Manage panel (leaders / booking managers): `/manage`
- First-user registration is only available when there are no users yet
- Later users are invited (to a team, or as oversight / system admin)

```bash
php artisan test
```

User stories live in [docs/stories](docs/stories/README.md).

## First slice

Activities and venues are first-class. Bookings use those foreign keys (not free-text “Climbing” / “Fox Coverts”). Members get mobile-friendly lists (upcoming / invites / rota), email RSVP links, a public share preview, and ICS feeds. There is no FullCalendar view in this slice.
