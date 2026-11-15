# Install

Sail-first local setup. Commands below assume you are on the host (outside the app container). If your shell is already attached to the `laravel.test` service (`LARAVEL_SAIL=1`), drop the `vendor/bin/sail` prefix and run `php artisan …`, `composer …`, and `npm …` directly.

## Prerequisites

- Docker with Compose (OrbStack or Docker Desktop)
- Composer on the host (to install PHP dependencies and the Sail binary)
- Access to the private Composer Satis for ralphjsmit packages:

```bash
composer config --global http-basic.satis.ralphjsmit.com USERNAME PASSWORD
```

## Bring up

```bash
composer install
cp .env.example .env
```

For Sail, point the app at the Compose MariaDB service and set `APP_URL` to the published HTTP port (default **80**):

```env
APP_URL=http://localhost

DB_CONNECTION=mariadb
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

Then:

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail npm install --ignore-scripts
vendor/bin/sail npm run build
```

`composer run setup` is aimed at a host PHP install (it runs `php artisan` and `npm` without Sail). Prefer the Sail commands above for this project.

The VS Code / Cursor Dev Container attaches straight to `laravel.test`; use that when you want an in-container terminal by default.

## URLs

With the default Compose ports:

- App: [http://localhost](http://localhost) — Members `/`, Teams `/teams`, Admin `/admin`
- Mailpit: [http://localhost:8025](http://localhost:8025)

## Accounts

- With an empty database, the first person can register.
- Later accounts come from team or oversight / system-admin invitations.
- Optional demo seed: `vendor/bin/sail artisan migrate --seed` creates `leader@example.com` / `password` (system admin and manager of a Climbing team).
- Agent browser checks may use the gitignored `.agent-accounts.json` file; do not commit it.

## Tests

```bash
vendor/bin/sail artisan test
# or
vendor/bin/sail composer test
```

## After install

Product notes, user stories, and other development detail live under [docs/](docs/README.md).

Legacy climbing-team data import is optional and documented in [docs/legacy-import-runbook.md](docs/legacy-import-runbook.md); it is not part of a normal install.
