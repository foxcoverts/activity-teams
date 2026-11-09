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

Production deploy runs migrate, then a **one-shot** import, under maintenance. See [`docs/deploy-setup.md`](deploy-setup.md) for secrets, variables, and the `DEPLOY_ENABLED` kill-switch.

1. Create a blank AT database; configure `DB_*` and co-located `LEGACY_DB_*` in the GitHub `production` environment (not a laptop connection).
2. Set `LEGACY_DOCUMENTS_PATH` to the on-server legacy upload parent if documents should be imported.
3. Freeze legacy writes (maintenance mode). Optionally take a safety SQL dump and tarball of `upload/`.
4. Set repository variable `DEPLOY_ENABLED=true` and deploy (merge to `main` or `workflow_dispatch`).
5. On the server, [`.deploy/post.sh`](../.deploy/post.sh) runs `migrate`, then if `storage/app/.legacy-imported` is missing:

```bash
php artisan legacy:import --documents-path="$LEGACY_DOCUMENTS_PATH"  # flag omitted when path empty
php artisan legacy:verify
touch storage/app/.legacy-imported
```

6. Spot-check login, qualifications, bookings, key holders, a document download, and calendar feeds if tokens were preserved.
7. Leave the legacy app offline or read-only; traffic remains on `https://climbfoxcoverts.co.uk/`.
8. Keep the legacy database until deferred todos/kit checks are imported (`--only=todos` / `--only=kit_checks` once those stories ship) or you explicitly abandon them. Then take a final dump and drop the legacy DB.

### Re-running import

Import runs only when `storage/app/.legacy-imported` is absent. To force a full re-import (destructive relative to already-imported data): put the site in maintenance, remove that marker on the server, fix env/data as needed, and re-deploy. If import or verify fails, the deploy stops and the site stays down.

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
