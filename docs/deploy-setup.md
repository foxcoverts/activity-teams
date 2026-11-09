# Deploy setup (product owner)

Do these after the workflow files land. Order matters where noted.

Production URL: **https://climbfoxcoverts.co.uk/**

Related files: [`.github/workflows/test.yml`](../.github/workflows/test.yml), [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml), [`.deploy/post.sh`](../.deploy/post.sh), [legacy import runbook](legacy-import-runbook.md).

## A. Repo workflow (do first)

1. Require pull requests to merge into `main` (branch protection).
2. Require the **Test** check to pass before merge.
3. Develop on branches / PRs only — do not push feature work straight to `main`.

## B. GitHub Actions access

1. Ensure Actions are enabled for `foxcoverts/activity-teams`.
2. Ensure the workflow can read repository / environment secrets (org policy allows the actions we use: `shivammathur/setup-php`, `easingthemes/ssh-deploy`, `appleboy/ssh-action`, `iamsauravsharma/create-dotenv`, etc.).

## C. GitHub Environment `production`

1. Create environment **`production`** with URL `https://climbfoxcoverts.co.uk/`.
2. Optional but useful: add required reviewers on that environment for an extra approval step on deploy.
3. Keep deploys blocked until cutover (see kill-switch below).

## D. Kill-switch (repository variable)

Set repository variable **`DEPLOY_ENABLED`** to `false` (or leave unset). The deploy job’s `if` condition reads this **repository** variable (environment-scoped vars are not reliable for job-level gates).

- `true` — allow deployment on push to `main` and `workflow_dispatch`
- unset / anything else — Test still runs; Deploy is skipped

## E. Secrets

### Repository secrets (required for Test on every PR)

Same Satis login Composer prompts for locally against `https://satis.ralphjsmit.com` (ralphjsmit account email + password). Workflows run:

`composer config http-basic.satis.ralphjsmit.com "$USERNAME" "$PASSWORD"`

before `composer install` ([ralphjsmit’s GitHub Actions notes](https://ralphjsmit.com/github-actions-private-composer)).

| Secret | Purpose |
|--------|---------|
| `RALPHJSMIT_COMPOSER_USERNAME` | Satis username (usually your email) |
| `RALPHJSMIT_COMPOSER_PASSWORD` | Satis password |

Keep these as **repository** secrets so both the Test workflow and the deploy job can read them.

### Secrets on `production` (preferred for deploy)

| Secret | Purpose |
|--------|---------|
| `APP_KEY` | Laravel app key written into deployed `.env` |
| `DB_PASSWORD` | Activity Teams database password |
| `LEGACY_DB_PASSWORD` | Read-only (preferred) legacy climbing-team DB password |
| `SSH_PRIVATE_KEY` | Deploy key; public half in IONOS `authorized_keys` |
| `MAIL_PASSWORD` | SMTP / mail auth password if used |
| `MAIL_WEBHOOK_SECRET` | ForwardEmail **Webhook Signature Payload Verification Key** (My Account → Domains → Settings). Used to verify `X-Webhook-Signature` (HMAC-SHA256 of the raw body). |
| Provider API keys | Any extra mail keys you already use (e.g. SendGrid / ForwardEmail) |

## F. Variables on `production`

| Variable | Purpose |
|----------|---------|
| `SSH_HOST` | IONOS SSH host |
| `SSH_USERNAME` | SSH user |
| `SSH_PORT` | SSH port |
| `SSH_PATH` | Absolute path to the web app root on the server |
| `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USERNAME` | Activity Teams DB |
| `LEGACY_DB_CONNECTION` / `LEGACY_DB_HOST` / `LEGACY_DB_PORT` / `LEGACY_DB_DATABASE` / `LEGACY_DB_USERNAME` | Co-located legacy DB |
| `LEGACY_DOCUMENTS_PATH` | Server path to legacy upload parent (omit if unused) |
| `MAILER` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_ENCRYPTION` / `MAIL_USERNAME` / `MAIL_FROM_ADDRESS` | Outbound mail as needed |
| `MAIL_INBOUND_DOMAIN` | Keep `climbfoxcoverts.co.uk` unless you change inbound later |

`APP_URL` is set in the deploy workflow to `https://climbfoxcoverts.co.uk` (change the workflow if the domain ever moves).

## G. IONOS / server (before first real deploy)

1. Set site PHP runtime to **8.5** (CLI binary `php8.5-cli` must exist for `.deploy/post.sh`).
2. Create a **blank** Activity Teams database; note host/name/user/password for the secrets/vars above.
3. Keep the legacy climbing-team database available (prefer read-only user); note `LEGACY_DB_*`.
4. Note the on-server path to legacy document uploads for `LEGACY_DOCUMENTS_PATH`.
5. Install/authorize the deploy SSH public key for `SSH_USERNAME`.
6. Confirm `SSH_PATH` is the directory that should receive the rsynced app (same model as climbing-team). On this host, **`.ssh` lives inside `SSH_PATH`** — never delete it.
7. If legacy document files still live under the web root (e.g. `upload/`), keep that path for `LEGACY_DOCUMENTS_PATH`; deploy rsync excludes `upload`.

## H. First cutover (when ready)

1. Freeze legacy writes / put legacy into maintenance as per the [import runbook](legacy-import-runbook.md).
2. Backup the current web root (tarball) and databases.
3. Optional: empty `SSH_PATH` **except** paths deploy must preserve (see below). Full-app rsync uses `--delete`, so old climbing-team code not in the new tree is removed automatically; excluded paths are left alone. Example wipe that matches the excludes:

```bash
cd "$SSH_PATH"   # the path from the GitHub variable
find . -mindepth 1 -maxdepth 1 \
  ! -name '.ssh' \
  ! -name 'storage' \
  ! -name 'upload' \
  ! -name 'logs' \
  -exec rm -rf {} +
```

4. Set repository variable `DEPLOY_ENABLED=true`.
5. Merge to `main` (or run **Deploy to Webspace** via `workflow_dispatch`).
6. Confirm Actions: Test passes → Deployment runs → remote `.deploy/post.sh` migrates, imports once, verifies, writes `storage/app/.legacy-imported`, then `up`.
7. Spot-check login, bookings, keys, a document, calendar feeds.
8. Leave `DEPLOY_ENABLED=true` for normal ongoing deploys, or set back to `false` to pause auto-deploy.

### Rsync `--delete` excludes

Deploy removes server files that are not in the build, except:

| Path | Why |
|------|-----|
| `.ssh` | Deploy keys live under `SSH_PATH` on this host |
| `storage` | Uploads, `storage/app/.legacy-imported`, logs, framework runtime |
| `upload` | Legacy climbing-team document files (import source) |
| `logs` | Host-level logs directory if present (not Laravel `storage/logs`) |

`.env` is **not** excluded: each deploy ships a fresh file from GitHub secrets/vars. The maintenance-only rsync (down file) does not use `--delete`.

## I. If something goes wrong

- **Import/verify failed:** site stays in maintenance; fix data/env; remove `storage/app/.legacy-imported` only if you need a full re-import; re-run deploy.
- **Pause deploys:** set `DEPLOY_ENABLED=false`.
- **Force re-import:** remove the marker on the server while the app is down, then re-deploy (destructive relative to already-imported data — follow the runbook).
