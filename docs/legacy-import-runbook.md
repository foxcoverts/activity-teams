# Legacy Climbing import runbook

One-shot cutover from `climbing-team` into Activity Teams for the Fox Coverts Climbing team.

## What you need

- New blank Activity Teams database on the server
- Existing legacy climbing-team database kept available (read-only user preferred)
- Document files from legacy `upload/` (two PDFs today)
- Committed catalog seed via `ClimbingCatalogSeeder` / `legacy:import` catalog step

## Local rehearsal (Sail)

Sail uses the **same MariaDB container** with a second database, `climbing_legacy` (not a second service). Fresh Sail volumes create it via `docker/mariadb/create-legacy-database.sh`. Existing volumes: load the dump with Artisan.

1. Ensure `.env` has:

```env
LEGACY_DB_CONNECTION=mariadb
LEGACY_DB_HOST=mariadb
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=climbing_legacy
LEGACY_DB_USERNAME=sail
LEGACY_DB_PASSWORD=password
```

2. Load the dump (creates the DB if needed; `--force` drops and recreates):

```bash
php artisan legacy:load-dump
# or: php artisan legacy:load-dump --force
```

Default dump path: `database/import/dbs12809208-full.sql`.

3. On the Activity Teams database:

```bash
php artisan migrate --no-interaction
php artisan legacy:import --dry-run
php artisan legacy:import --documents-path=/path/to/legacy/upload-parent
php artisan legacy:verify
```

Use `vendor/bin/sail …` from the host if you are outside the Sail app container.

## Server cutover

1. Create a blank AT database; deploy Activity Teams; run `php artisan migrate --no-interaction`.
2. Point `DB_*` at the new AT database.
3. Point `LEGACY_DB_*` at the **co-located** legacy climbing-team database (not a laptop connection).
4. Freeze legacy writes (maintenance mode). Optionally take a safety SQL dump and tarball of `upload/`.
5. Run:

```bash
php artisan legacy:import --documents-path=/path/to/legacy/upload-parent
php artisan legacy:verify
```

6. Spot-check login, qualifications, bookings, key holders, a document download, and calendar feeds if tokens were preserved.
7. Point traffic at Activity Teams; leave the legacy app offline or read-only.
8. Keep the legacy database until deferred todos/kit checks are imported (`--only=todos` / `--only=kit_checks` once those stories ship) or you explicitly abandon them. Then take a final dump and drop the legacy DB.

## Importer domains

Core (default): `catalog`, `users`, `qualifications`, `keys`, `bookings`, `news`, `documents`, `notifications`, `changes`.

Deferred stubs (refuse until built): `todos`, `kit_checks`.

```bash
php artisan legacy:import --only=users --only=bookings
```

## Mapping reminders

- Testing bookings are dropped (`activity` or `group_name` = `Testing`).
- Maintenance → Climbing + Team + label; Tower Inspection / Kit Checks → Climbing Access + Team + required label.
- Splash Camp / Wake up the Woods / District AGM → Other + `requires_lead_instructor=false`.
- Key names containing “Warden” → Campsite Warden; others → Campsite Climbing. Campsite Warden covers Campsite Climbing.
- Legacy `suspended` users with no attendance other than `needs-action` are skipped (qualifications alone do not keep them). Remaining suspended users are imported unsuspended and are not added to the Climbing team.
- News markdown rewrites `/rota` → `/bookings?tab=rota` and `/invite` → `/bookings?tab=invites` (including absolute `climbfoxcoverts.co.uk` links).
- Booking `changes` become activity-log entries: field updates → `updated` (with `attribute_changes`), attendance → `attendance_updated`, free-text → comments. Cancellation reasons stay on the activity description. Legacy has no create change, so an uncaused `created` activity is synthesised from each booking’s `created_at`.
