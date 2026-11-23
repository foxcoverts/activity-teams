# Docs

Development and product detail for Activity Teams. For a short capability overview see the root [README](../README.md); for local bootstrap see [INSTALL](../INSTALL.md).

Activity Teams replaces [`foxcoverts/climbing-team`](https://github.com/foxcoverts/climbing-team): same domain ideas, new Filament UI rather than a port of the old Blade layouts. [climbing-team PR 303](https://github.com/foxcoverts/climbing-team/pull/303) is the cautionary tale for not migrating that UI in place. Cutover status and acceptance detail live with the [user stories](stories/README.md).

- [Development](development.md) — human-friendly working conventions
- [User stories](stories/README.md) — backlog, roles, and relaunch scope
- [Acceptance checklist](stories/acceptance-checklist.md) — live verification
- [Mobile / PWA notes](mobile-pwa.md) — on-the-day priorities, Phase 0 shell, later offline constraints
- [Deploy setup](deploy-setup.md) — GitHub Actions, secrets, and production cutover
- [Legacy import runbook](legacy-import-runbook.md) — optional climbing-team data import

Agent-oriented rules live in root [AGENTS.md](../AGENTS.md) (mirrored in [CLAUDE.md](../CLAUDE.md)).
