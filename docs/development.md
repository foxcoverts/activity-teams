# Development

Human-oriented working notes for this repository. Local bootstrap is in [INSTALL.md](../INSTALL.md). Product backlog and roles are in [stories/README.md](stories/README.md). Agent-oriented rules and the full Laravel Boost dump live in root [AGENTS.md](../AGENTS.md) (mirrored as [CLAUDE.md](../CLAUDE.md)); keep the two agent files in sync when you change shared guidance.

## Day to day

- Prefer **Laravel Sail** (or the Dev Container attached to `laravel.test`). Outside the container, prefix app commands with `vendor/bin/sail`; inside it (`LARAVEL_SAIL=1`), run `php artisan`, `composer`, and `npm` directly.
- Do not change application dependencies without agreement.
- Prefer feature tests with factories over one-off verification scripts. Run the narrowest useful test set for the change (`vendor/bin/sail artisan test --compact …`).
- After PHP edits, format with Pint (`vendor/bin/sail bin pint --dirty`).
- **Apostrophe stress (optional, local only):** temporarily bias `UserFactory` / `TeamFactory` defaults to names with apostrophes, run the full suite once, fix any Class A/B assertion flakes, then revert the factory defaults. Do not leave that bias on permanently or as a CI gate. Prefer `withApostropheInName()` for intentional escaping regressions.
- When a change meets, partly meets, or stops meeting a story’s acceptance criteria, update that story’s status in the same change ([stories/README.md](stories/README.md)).

## Spelling

- **Code** (identifiers, columns, config, routes, CSS/JS, test method names): US English (`color`, `favor`, `normalize`).
- **User-facing copy** (Filament labels, helpers, notifications, member markdown, `__()` / lang strings): British English (`colour`, `organise`, `normalise`), unless quoting a proper noun or third-party term.

## Authorisation

`User::isSystemAdmin()` does **not** imply `manages($team)`. Prefer `manages($team)` (or lead checks) for booking create/update, attendance override/rollcall, debrief complete, and manager booking triage. Allow admin-without-membership only where product rules already do (equipment sets, catalog give, members/roles, Manager bounce dashboard access without triage). See roles in [stories/README.md](stories/README.md).

## UI and Filament

- Use `App\Support\Palette` for semantic colours (`positive`, `destructive`, `uncertain`, `neutral`, `manager`), not hard-coded Filament colour names.
- Enums in the UI: `HasLabel`; add `HasColor` / `HasIcon` when values need to be told apart. `BookingStatus` is the pattern (confirmed / tentative / cancelled). Climbing-team [PR 303](https://github.com/foxcoverts/climbing-team/pull/303) enums are a useful reference.
- Icons: Heroicon first; Lucide only when Heroicons has no suitable glyph. Do not add further icon packages without agreement.
- Navigation within a Filament group stays alphabetical by label (`navigationSort` densely from 1). Nested items stay with their parent.
- Forms and infolists: every field in a `Section` with icon, single column, full span. Multiple root sections are fine when the groups are distinct.
- Empty infolist values: `->placeholder('-')`.
- Booking dates/times: no seconds. No record IDs in user-facing screens. Slugs only on edit forms.
- Do not show another person’s email, phone, or emergency-contact details in multi-person lists or tables; keep those on individual-person screens under US-58 rules.
- Member-facing copy: do not mention system admin or other internal roles members do not use.
- Related-record removal wording: **Remove** / **Remove selected** / **Removed** (not detach jargon).
- Dashboard widget meta rows use **chips only** (not Filament badges mixed into that chip row).

## Mail and inbox

Outbound mail that can leave the request should be queued (`ShouldQueue`, `afterCommit()`, `$deleteWhenMissingModels = true`), except booking invite/update/confirm/cancel mail which already leaves via `SendBookingNotification` — do not double-queue those.

Inbox notices need a real `inboxUrl()` destination. When a notice’s own email is sent, use `InboxNotification::markReadForDelivery()` so both `read_at` and `digest_notified_at` are set (multi-item digests stamp only `digest_notified_at`).

## Further reading

- [Deploy setup](deploy-setup.md)
- [Legacy import runbook](legacy-import-runbook.md)
- [AGENTS.md](../AGENTS.md) — full agent and framework ruleset
