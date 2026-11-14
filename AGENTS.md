# AI and Credit Efficiency

- Keep AI work proportionate: use the smallest relevant context, the fewest useful tool calls, and focused tests. Avoid broad repository scans, repeated reads, speculative subagents, and external research when the answer is already clear locally.
- Proceed without asking when the task is bounded, the desired behavior is clear, and implementation is likely more efficient than handing it back.
- Before substantial open-ended research or multi-step work, assess whether it is best done by Copilot/Cursor, Gemini, or the product owner. If the task depends on live legacy behavior, operational access, product judgment, or real-world acceptance, ask the product owner to investigate or decide rather than guessing from source code.
- If broad independent reasoning or alternative prioritization is needed, suggest Gemini and offer a concise, sanitized prompt the product owner can use. Never send repository code, private data, credentials, or other sensitive material to Gemini or another external service.
- When a task is likely to consume significant credits or its direction is uncertain, pause before doing that work. Briefly state what is known, what needs resolving, and the lower-cost options; ask whether to continue with Copilot/Cursor, have the product owner investigate or implement a part, or use Gemini for independent reasoning. Do not pause for routine, clearly scoped implementation.
- Use the product owner's strong domain and backend experience: keep domain decisions and legacy interpretation with them; favor AI assistance for bounded Livewire, front-end, and feature-test implementation and iteration.

## Local browser accounts

The gitignored file `.agent-accounts.json` lists local logins that are safe for browser checks. It is not seeded and must not be committed. A fresh database still has no users so the first person can register.

- Before signing into the local app, read that file and pick the account whose `use_for`, teams, and panels match the check. Do not inspect the database to choose a login when the file already has a suitable entry.
- Never sign in as a real person's mailbox (for example a Gmail address) unless that person listed it in the file.
- Prefer an existing account. Create a new local user for browser work only when none fits. Use an `@example.test` address, a known password, and append an entry with name, email, password, panels, teams (name, slug, role), flags, and `use_for`.
- If you change an account you created, update its entry. If a listed password fails, stop and tell the product owner; do not reset a password you did not just set.
- Feature tests keep using factories. Do not create users only to fill this file.

## Filament actions on Livewire components

On Livewire components that use `InteractsWithActions`, Filament resolves mounted actions by method name after each request. `Action::make('assign')` must be returned from `assignAction()` (or a method literally named `assign`). Nested actions in an `ActionGroup` follow the same rule. A mismatch (for example `Action::make('assignTask')` on `assignAction()`) renders the trigger but clicks do nothing. Prefer `BookingTasks` / `TaskAssigneePanel` as the naming pattern, and cover new panel actions with `callAction` / `mountAction` tests.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

## Command Execution Context

This repository's `.devcontainer/devcontainer.json` attaches VS Code to the `laravel.test` service in `compose.yaml`, which is the Laravel Sail application container. When the current terminal is already in that container, run application commands directly: `php artisan ...`, `composer ...`, `npm ...`, and `php ...`. Do not wrap them in `vendor/bin/sail`; that would unnecessarily try to start another container.

Before choosing a command form, check the current shell for `LARAVEL_SAIL=1` (for example, `printenv LARAVEL_SAIL`) and consult the Dev Container service configuration if needed. A generic container marker alone does not prove the shell is in Sail. If the shell is outside the Sail application container, use `vendor/bin/sail` for commands that need the app runtime, provided Sail is available.

Sail lifecycle and orchestration commands such as `up`, `stop`, and `open` are different: run them from a shell with access to Docker Compose, not from inside the application container. Commands shown below with a `vendor/bin/sail` prefix are for shells outside the Sail application container; use the equivalent direct command when already inside it.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` inside the Sail application container, or `vendor/bin/sail composer show --direct` outside it. Use the same context-dependent form for `composer show <vendor/package>`.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.
- Spelling: use US English in code (PHP identifiers, DB columns, config keys, routes, CSS/JS identifiers, test method names — e.g. `color`, `favor`, `normalize`). Use British English in user-facing interface copy (Filament labels, helper text, notifications, member-facing markdown, `__()` / lang strings — e.g. `colour`, `organise`, `normalise`), unless quoting a proper noun or third-party term.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project uses Laravel Sail, and its VS Code Dev Container attaches directly to the `laravel.test` Sail service. Follow the command execution context rules above.
- From outside the app container, start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`. Run `vendor/bin/sail open` from a shell with Docker Compose access.
- Inside the app container, run application commands directly. For example: `php artisan migrate`, `composer install`, `npm run dev`, or `php [script]`.
- Outside the app container, prefix app-runtime commands with `vendor/bin/sail`. For example: `vendor/bin/sail artisan migrate`, `vendor/bin/sail composer install`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail php [script]`.
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.
- Negative UI assertions (`assertDontSee` / `assertDontSeeHtml`) stay when they lock visibility or toggle behaviour — empty widgets, excluded records, actions that disappear after a state change, and similar. Do not keep negatives that only prove discarded copy or temporary scaffolding from an earlier draft is gone; assert the desired positive copy instead. Boy-scout those leftovers when a test file is already being changed; do not open unrelated tests only to strip them.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `vendor/bin/sail artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

## Queued Mail

Outbound email must leave the request on the queue. New mailables and mail notifications implement `ShouldQueue`, call `$this->afterCommit()` in the constructor, and set `$deleteWhenMissingModels = true`. `use Queueable` alone does not queue delivery.

Booking invite, update, confirm, and cancel mail already leaves the request through `SendBookingNotification`. Do not add `ShouldQueue` to those mailables, or the debounce job will enqueue a second hop. Keep `Mail::send()` inside that job.

Use `Mail::assertQueued()` for queued mailables and `Mail::assertSent()` for mail sent inside `SendBookingNotification`.

## Inbox notifications

Every deferrable notice stored through `RecordsInbox` has a URL from `inboxUrl()`. The inbox row opens the resource view page. That page marks the row read and redirects to the URL. It does not render the notice.

Give every new notice a destination: the booking, key, team, person, or list the reader should open. Do not send a notice from a branch where `inboxUrl()` would return null.

If a notice truly cannot have a destination, stop and change `ViewNotification` so it shows the message instead of redirecting. Do not add that screen before such a notice exists.

The unread badge and inbox list use `read_at`. The digest outstanding queue is rows with both `read_at` and `digest_notified_at` null. When you send that notice's own email (immediate optional mail, or a one-item digest replay), call `InboxNotification::markReadForDelivery()` so both timestamps are set. Stamping only `digest_notified_at` skips the digest but leaves the row unread. A multi-item digest is the exception: it stamps only `digest_notified_at` so the inbox stays unread. Preference-blocked sends leave both null.

## Application Palette

Use `App\Support\Palette` for semantic colors in Filament components and status enums instead of hard-coded Filament color names or `Color` constants:

- `Palette::positive()` for affirmative or confirmed states (green).
- `Palette::destructive()` for negative, cancellation, or removal states (red).
- `Palette::uncertain()` for tentative or undecided states (amber).
- `Palette::neutral()` for present but undecided or informational states (gray).
- `Palette::manager()` for team-manager identity (sky).

Use the palette method that matches the meaning of the UI, not merely its appearance. If a reusable semantic color is needed and none fits, extend `Palette` with a clearly named method rather than adding a one-off color. The panel's primary color is configured separately.

## Filament icons

Prefer icons in this order:

1. **Heroicon** (`Filament\Support\Icons\Heroicon`) whenever a suitable glyph exists. Use outlined vs solid the way neighbouring Filament code already does.
2. **Lucide** (`mallardduck/blade-lucide-icons`, Blade name `lucide-{name}`) only when Heroicons has no suitable glyph. Pass that string to `->icon()` / `HasIcon::getIcon()`.
3. Do not add further icon packages without approval. Do not migrate existing Heroicons to Lucide, and do not replace Filament chrome icons.

## Filament UI Conventions

- **Navigation within a group:** keep items alphabetical by navigation label. When adding or renaming an item, renumber that group's `navigationSort` values densely from 1 so the order stays alphabetical (e.g. Teams **Setup**: Activities, Equipment sets, Venues). Nested items under a parent (e.g. Debriefs under Bookings) stay with their parent and do not reshuffle siblings in the parent group. Ungrouped items such as dashboards keep their existing negative sorts outside this rule.
- When an enum is shown in Filament, implement `HasLabel`; add `HasColor` and `HasIcon` when they help distinguish values. Return translated labels, semantic colors from `App\Support\Palette`, and icons following the Filament icons hierarchy. Apply this when the enum is in scope rather than restyling unrelated enums. See `docs/stories/README.md` for the project's reference.
- Use **Remove**, **Remove selected**, and **Removed** in user-facing actions that take related records off a parent. Keep Filament's detach/dissociate action types and existing localized wording overrides; do not restore relationship jargon in labels, modal copy, or notifications.
- Do not display seconds in booking dates or times.
- Do not display record IDs in user-facing screens.
- Show slugs only on edit forms, never in lists, view screens, or create forms.
- Do not show another person's email address, phone number, or emergency-contact details in multi-person lists or tables. Keep those details on individual-person screens where US-58 acknowledgement and existing authorization can be enforced. Only make an exception when a user story explicitly requires it, and preserve its access and time limits.
- **Forms and infolists:** every field lives in a `Section` with `->icon(…)->columns(1)->columnSpanFull()`. Do not leave fields on Filament’s default multi-column schema. **More than one root section is allowed** when the groups are distinct; do not collapse everything into a single wrap. Pattern: Member (one section), User (**User** and **Access** as siblings), and the booking **form and infolist** (`BookingResource`; the member booking view uses the same infolist section). Booking sections also set `iconColor` from the status enum.
- **Repeaters / grouped fields:** stack inner fields unless a true side-by-side pair is required. The booking form’s fused start/end schedule groups are the example of that exception; Keys and Locks have no such pair.
- **Empty values:** use `->placeholder('-')` on infolist entries that may be blank.
- **Member-facing copy:** do not mention system admin, or other internal roles the member does not use.
- **Selects that mix a special destination with people:** keep the special option visually separate (ungrouped or its own group) and wrap the people in an optgroup, as Pass does for cupboard vs Members.
- **Dashboard widget meta:** item rows on member/team dashboard widgets use **chips only** for due time, priority, team, venue, and similar meta. Do not mix Filament badges into that chip row. Colour a chip with `get_color_css_variables(...)` and the palette/`HasColor` colour when it needs semantic colour (priority, team brand). Filament badges stay on tables, forms, infolists, and section chrome — not in widget chip rows.
- **Boy scout:** when already working in an area, bring nearby code up to the standards that apply there; do not open unrelated files only to clean them. Known cases: Filament forms/infolists already being changed → this layout; test files already being changed → drop discarded-copy `assertDontSee` scaffolding (see Testing).
- **Textarea submit shortcut:** when a Filament action modal (or similar panel form) includes a `Textarea` where submitting from the keyboard is useful, wire `App\Filament\Support\SubmitsOnModEnter::attributes()` via `extraAlpineAttributes` so Cmd/Ctrl+Enter submits the enclosing form while plain Enter keeps newlines. Prefer that helper over one-off Alpine. Do not use it where Enter already means send (booking comments use Enter / Shift+Enter).

## Public form pages

Pages that use `layouts.filament-form` (mail-preference links from email, invitation create-account, and similar token or signed-out forms) are not Filament panel schemas. That layout loads CDN Tailwind without `@tailwindcss/forms`, so a class like `border-slate-300` with no `border` or padding renders as a naked native control.

Style those controls with the shared Blade components, not ad-hoc input classes:

- `x-form.input` for text, email, and password fields (`rounded border`, `px-3 py-2`)
- `x-form.select` for selects (`appearance-none`, `pl-3 pr-10`, chevron)

Labels are `block text-sm font-medium`. Helper copy is `text-sm` or `text-xs font-normal` and does not replace a visible field border. When adding a public form on this layout, reuse the components. Do not invent a third set of input classes. Filament panel pages keep using Filament fields.

## User Story Progress

When a code change meets, partly meets, or stops meeting a user story's acceptance criteria, update that story in the same change. Stories are in `docs/stories/`; keep each file's frontmatter status in sync with its entry in `docs/stories/README.md`.

- Compare the change against the story's acceptance criteria.
- Use `done` only when every criterion is met in this repository.
- Use `partial` when some criteria are met and some remain; describe the remaining work in the story file. Criteria for functionality not yet built remain open.
- Leave unrelated `not-started` stories unchanged.

=== ralphjsmit/laravel-filament-activitylog/core rules ===

## Filament Activitylog Pro

Easily add beautiful timelines to your Filament apps. Works out-of-the box & integrates with Spatie Activitylog. Its full documentation is available to you as machine-readable Markdown.

- Before writing or changing code that uses this package, consult the docs — do not guess the API.
- Start at the index: `https://filamentplugins.com/filament-activitylog-pro/docs/v2/llm/llms.txt`, then fetch the specific `<group>/<page>.md` files you need. Each `.md` page is fully assembled. For the whole documentation in one file, use `llm/llms-full.txt` at the same path.
- The `filament-activitylog-pro` skill describes this workflow in detail — use it whenever you work with this package.

=== spatie/laravel-activitylog/core rules ===

# spatie/laravel-activitylog

Activity logging package for Laravel. Logs model events and manual activities to a database table.

## Key Concepts

- **Activity**: An Eloquent model (`Spatie\Activitylog\Models\Activity`) storing log entries with subject, causer, event, attribute_changes, and properties.
- **Subject**: The model being acted upon (polymorphic `subject_type`/`subject_id`).
- **Causer**: The model that caused the action, typically the authenticated user (polymorphic `causer_type`/`causer_id`).
- **LogOptions**: Fluent configuration object returned by `getActivitylogOptions()` on models using the `LogsActivity` trait.
- **ActivityEvent**: Enum with cases `Created`, `Updated`, `Deleted`, `Restored`.
- **`attribute_changes`** column: stores `{"attributes": {...}, "old": {...}}` for tracked model changes.
- **`properties`** column: stores custom user data set via `withProperties()`.

## Traits

### `LogsActivity`

Add to models to automatically log create/update/delete events. Optionally implement `getActivitylogOptions()` to configure which attributes to track (defaults to logging events without attribute changes).

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Article extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

### `CausesActivity`

Add to user/causer models. Provides `activitiesAsCauser()` relationship.

### `HasActivity`

Combines `LogsActivity` and `CausesActivity`. Provides `activities()`, `activitiesAsSubject()`, and `activitiesAsCauser()`.

## Manual Logging

```php
activity()
    ->performedOn($article)
    ->causedBy($user)
    ->event(ActivityEvent::Updated)
    ->withProperties(['key' => 'value'])
    ->log('Article was updated');
```

## LogOptions Methods

| Method                                  | Description                                      |
| --------------------------------------- | ------------------------------------------------ |
| `logFillable()`                         | Log all fillable attributes                      |
| `logAll()`                              | Log all attributes                               |
| `logOnly(array)`                        | Log specific attributes                          |
| `logExcept(array)`                      | Exclude attributes                               |
| `logOnlyDirty()`                        | Only log changed attributes                      |
| `dontLogEmptyChanges()`                 | Skip logging when no tracked attributes changed  |
| `dontLogIfAttributesChangedOnly(array)` | Ignore updates that only change these attributes |
| `useLogName(string)`                    | Set custom log name                              |
| `setDescriptionForEvent(Closure)`       | Custom description per event                     |
| `useAttributeRawValues(array)`          | Store raw (uncast) values                        |

## Querying Activities

```php
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Enums\ActivityEvent;

Activity::forEvent(ActivityEvent::Created)->get();
Activity::causedBy($user)->get();
Activity::forSubject($article)->get();
Activity::inLog('orders')->get();
```

## Setting the causer

Override the causer for a block of code:

```php
use Spatie\Activitylog\Facades\Activity;

Activity::defaultCauser($admin, function () {
    // all activities here are caused by $admin
});

// or set globally for the rest of the request
Activity::defaultCauser($admin);
```

## Disabling Logging

```php
activity()->withoutLogging(function () {
    // no activities logged here
});
```

## Accessing Changes and Properties

```php
$activity = Activity::latest()->first();

// Tracked model changes (set automatically by LogsActivity)
$activity->attribute_changes; // Collection: {"attributes": {...}, "old": {...}}

// Custom user data (set via withProperties)
$activity->properties; // Collection
$activity->getProperty('key'); // single value
```

## Custom Activity Model

Set `activity_model` in `config/activitylog.php` to a class that extends `Model` and implements `Spatie\Activitylog\Contracts\Activity`. Use a custom model for custom table names or database connections.

## Customizing Actions

The package uses action classes (`LogActivityAction`, `CleanActivityLogAction`) that can be extended and swapped via config:

```php
// config/activitylog.php
'actions' => [
    'log_activity' => \App\Actions\CustomLogActivityAction::class,
    'clean_log' => \App\Actions\CustomCleanAction::class,
],
```

Custom action classes must extend the originals. Override protected methods (`save()`, `beforeActivityLogged()`, `resolveDescription()`, etc.) to customize behavior.

## Configuration

Key config options in `config/activitylog.php`:

- `enabled`: Master on/off switch (env: `ACTIVITYLOG_ENABLED`)
- `clean_after_days`: Days to keep records for `activitylog:clean` command
- `default_log_name`: Default log name (string)
- `default_auth_driver`: Auth driver for causer resolution
- `include_soft_deleted_subjects`: Include soft-deleted subjects
- `activity_model`: Custom Activity model class
- `default_except_attributes`: Globally excluded attributes
- `actions.log_activity`: Action class for logging activities
- `actions.clean_log`: Action class for cleaning old activities

</laravel-boost-guidelines>
