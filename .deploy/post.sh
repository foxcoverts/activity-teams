#!/bin/bash
set -euo pipefail

PHP=php8.5-cli
MARKER=storage/app/.legacy-imported

echo "Running post-deploy scripts"

mkdir -p storage/app/public storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Defensive: stay in maintenance even if the pre-rsync down file was missing
$PHP artisan down --render=down.deploy --retry=60 --refresh=15 || true

# Migrate before optimize:clear — database cache/session stores need their tables first.
$PHP artisan migrate --force -n

$PHP artisan tinker --execute '
foreach (["cache", "sessions", "jobs"] as $table) {
    if (! Illuminate\Support\Facades\Schema::hasTable($table)) {
        throw new RuntimeException("Required table missing after migrate: {$table}");
    }
}
'

$PHP artisan optimize:clear || true
$PHP artisan storage:link --force -n

if [[ ! -f "$MARKER" ]]; then
    echo "Running one-shot legacy import"

    LEGACY_DOCUMENTS_PATH="${LEGACY_DOCUMENTS_PATH:-}"
    if [[ -z "$LEGACY_DOCUMENTS_PATH" && -f .env ]]; then
        LEGACY_DOCUMENTS_PATH="$(sed -n 's/^LEGACY_DOCUMENTS_PATH=//p' .env | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tail -n1)"
    fi

    if [[ -n "$LEGACY_DOCUMENTS_PATH" ]]; then
        $PHP artisan legacy:import --documents-path="$LEGACY_DOCUMENTS_PATH"
    else
        $PHP artisan legacy:import
    fi

    $PHP artisan legacy:verify
    touch "$MARKER"
fi

$PHP artisan optimize
$PHP artisan up

echo "Deployment finished!"
