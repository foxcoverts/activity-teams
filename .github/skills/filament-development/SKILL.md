---
name: filament-development
description:
  Builds Filament application and admin panel interfaces. Use when creating,
  modifying, testing, or debugging Filament panels, resources, relation managers,
  pages, schemas, forms, tables, actions, infolists, widgets, imports, or exports.
---

# Filament Development

- Filament is a Laravel UI framework built on Livewire, Alpine.js, and Tailwind CSS. UIs are defined in PHP via fluent, chainable components. Follow existing conventions in this app.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

## Artisan

- Before creating a Filament resource, page, relation manager, widget, or other generated component, check the installed Filament version in `composer.json`, discover the available `make:filament-*` commands with `php artisan list`, and inspect the chosen command's `--help`.
- Run the most specific suitable generator with the required options and `--no-interaction`. Follow the repository's Laravel/Sail command-context instructions in `AGENTS.md` (`php artisan` inside the app container; `vendor/bin/sail artisan` outside it).
- Treat generated files as the version-matched scaffold, not as finished application code. Read every generated file, preserve the current Filament API and lifecycle, then adapt it to this app's explicit architecture, authorization, UX, naming, and testing conventions. Do not replace the generator's scaffold by copying a nearby resource wholesale, especially when that example conflicts with current docs or project rules.
- If no suitable generator exists, or its output cannot sensibly serve the requested component, create the files manually using the installed-version docs and the closest relevant project patterns. Keep the implementation focused and explain any necessary deviation.

## Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

### Conditional form field visibility

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),
```

Use `Set $set` inside `->afterStateUpdated()` on a `->live()` field to mutate another field reactively. Prefer `->live(onBlur: true)` on text inputs to avoid per-keystroke updates:

### Reactive field update

```php
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

TextInput::make('title')
    ->required()
    ->live(onBlur: true)
    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
        'slug',
        Str::slug($state ?? ''),
    )),

TextInput::make('slug')
    ->required(),
```

Compose layout by nesting `Section` and `Grid`. Children span one column by default. Use `->columnSpan()` to span multiple columns or `->columnSpanFull()` to span the full width:

### `Section` and `Grid` layout

```php
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

Section::make('Details')
    ->schema([
        Grid::make(2)->schema([
            TextInput::make('first_name'),
            TextInput::make('last_name'),
            TextInput::make('bio')
                ->columnSpanFull(),
        ]),
    ]),
```

Use `Repeater` for inline `HasMany` management. `->relationship()` with no args binds to the relationship matching the field name:

### `Repeater` for `HasMany`

```php
use Filament\Forms\Components\Repeater;

Repeater::make('qualifications')
    ->relationship()
    ->schema([
        TextInput::make('institution')
            ->required(),
        TextInput::make('qualification')
            ->required(),
    ])
    ->columns(2),
```

Use `state()` with a `Closure` to compute derived column values:

### Computed table column value

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),
```

Use `SelectFilter` for enum or relationship filters, and `Filter` with a `->query()` closure for custom logic:

### Table filters

```php
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

SelectFilter::make('status')
    ->options(UserStatus::class),

SelectFilter::make('author')
    ->relationship('author', 'name'),

Filter::make('verified')
    ->query(fn (Builder $query) => $query->whereNotNull('email_verified_at')),
```

Actions are buttons that encapsulate optional modal forms and behavior:

### Action with modal form

```php
use Filament\Actions\Action;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data)),
```

### Wizard in an action modal

Use the action's own `->steps()` for a multi-step modal. Never place a `Wizard` component inside `->schema()`, `->form()` or `createOptionForm()`: the modal then wraps the whole wizard in one `<form>`, so pressing Enter in any step submits the action and skips the remaining steps. With `->steps()` the action drops that wrapper and each step handles Enter itself (next step, or submit on the last step).

```php
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;

Action::make('create')
    ->steps([
        Step::make('Details')->schema([/* ... */]),
        Step::make('Confirm')->schema([/* ... */]),
    ])
    ->modifyWizardUsing(fn (Wizard $wizard): Wizard => $wizard->submitAction(/* custom final button */))
    ->action(fn (array $data) => /* ... */),
```

- `->steps()` replaces the modal footer; the wizard renders its own Back, Next, Cancel and submit buttons. `modalSubmitAction()` and `modalCancelAction()` do not apply, so do not call `modalSubmitAction(false)` on a wizard.
- Customise the wizard with `->modifyWizardUsing()`, plus `->startOnStep()` and `->skippableSteps()` on the action. A custom `submitAction()` must call `$wire.callMountedAction()` itself, because there is no form to submit. Resolve anything that needs the wizard's state path lazily (for example in an `Htmlable::toHtml()`), as the wizard has no container when the callback runs.
- On a `Select`, keep `->createOptionForm()` returning the steps (it makes the create-option action exist), then call `->steps()` and `->modifyWizardUsing()` inside `->createOptionAction()`. Persist the result in `->createOptionUsing()`.
- On a resource `CreateRecord` page use the `HasWizard` concern and `getSteps()` instead. That page has no form wrapper either.
- A step's fields are only validated when moving forward, so check any cross-step rule again in the final action.

## Boy scout rule

When modifying an existing Filament resource, page, widget, form, infolist, table, or action, bring the surface being changed into line with the applicable project conventions in `AGENTS.md` where it is safe and reasonably within scope. For example, when a form or infolist is already being changed, apply the required section layout; use the semantic palette and icon guidance for components in scope.

Keep this cleanup bounded to the component or screen being touched. Do not restyle unrelated resources or expand a focused change into a broad UI redesign. If conformity would require unrelated behavior changes or substantial scope, leave that work alone and call it out.

## Testing

Use the installed Filament major version's testing APIs. Check `composer.json` and consult the matching official docs before relying on an assertion or helper:

- [Testing resources](https://filamentphp.com/docs/5.x/testing/testing-resources)
- [Testing schemas](https://filamentphp.com/docs/5.x/testing/testing-schemas)
- [Testing tables](https://filamentphp.com/docs/5.x/testing/testing-tables)
- [Testing actions](https://filamentphp.com/docs/5.x/testing/testing-actions)

This project uses PHPUnit and `Livewire::test(...)`. Follow the surrounding tests rather than introducing Pest syntax or a Pest plugin.

- Authenticate as a user authorized for the panel. Set the current Filament panel and tenant when the page requires them.
- Test the application's contract, not Filament internals. Prefer assertions for the Filament surface being tested: table assertions for columns and records, form assertions for fields and validation, schema assertions for infolist entries and layout components, and action assertions for actions.
- Use component keys, not display labels, for schema assertions. Resource view pages use the `infolist` schema; pass that name explicitly when needed, for example `assertSchemaComponentDoesNotExist('id', 'infolist')`.
- For visible copy, use `assertSeeText()` or `assertDontSeeText()` with distinctive text. Avoid broad raw-HTML checks such as `assertDontSee('ID')`, which can match markup or generated identifiers that aren't visible to the user.
- For writes, assert the relevant persisted state and observable side effects as well as the Livewire result. Edit pages use `['record' => $record->getKey()]` and `->call('save')`; assert redirects only when the page configures one.

### Table test

```php
use Livewire\Livewire;

Livewire::test(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));
```

### Create resource test

```php
Livewire::test(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertHasNoFormErrors();

$this->assertDatabaseHas('users', [
    'name' => 'Test',
    'email' => 'test@example.com',
]);
```

### Edit resource test

```php
Livewire::test(EditUser::class, ['record' => $user->getKey()])
    ->fillForm(['name' => 'Updated'])
    ->call('save')
    ->assertNotified()
    ->assertHasNoFormErrors();

$this->assertDatabaseHas('users', [
    'id' => $user->getKey(),
    'name' => 'Updated',
]);
```

### Testing validation

```php
Livewire::test(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();
```

Use `->callAction(DeleteAction::class)` for page actions, or `->callAction(TestAction::make('name')->table($record))` for table actions:

### Calling actions

```php
use Filament\Actions\Testing\TestAction;

Livewire::test(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();
```

### Testing schema components

Use schema assertions to check that a form field or infolist entry exists, is absent, or is configured as intended. For example:

```php
Livewire::test(ViewUser::class, ['record' => $user->getKey()])
    ->assertSchemaComponentDoesNotExist('id', 'infolist');
```

Use `assertFormFieldExists()` / `assertFormFieldDoesNotExist()` for form fields, and `assertSchemaComponentExists()` / `assertSchemaComponentDoesNotExist()` for other schema components. For table columns, use table assertions such as `assertTableColumnDoesNotExist()` instead of checking rendered markup.

### Testing wizards

Drive wizard steps with `->goToNextWizardStep()` and `->goToPreviousWizardStep()`, and set state with `->fillForm()`. `fillForm()` disables state-update hooks, so it does not run `->afterStateUpdated()` callbacks; use `->set('data.field', $value)` when a test depends on a hook (for example one field clearing another).

For a wizard in an action modal, call `->mountFormComponentAction()` or `->mountAction()` and assert with `assertMountedActionModalSee()`. Real keypresses cannot be tested, so assert the rendered step handler instead, for example `assertMountedActionModalSeeHtml('isLastStep() ? $wire.callMountedAction() : requestNextStep()')`.

## Correct namespaces

- Form fields (`TextInput`, `Select`, `Repeater`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Table columns (`TextColumn`, `IconColumn`, etc.): `Filament\Tables\Columns\`
- Table filters (`SelectFilter`, `Filter`, etc.): `Filament\Tables\Filters\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: prefer `Filament\Support\Icons\Heroicon` (e.g., `Heroicon::PencilSquare`) whenever a suitable glyph exists; use Lucide (`lucide-{name}` from `mallardduck/blade-lucide-icons`) only when Heroicons has no suitable glyph. Do not add further icon packages without approval.

## Common mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, `Fieldset`, and `Repeater` do not span all columns by default.
- **Use `Select::make('author_id')->relationship('author', 'name')` for `BelongsTo` fields.** `BelongsToSelect` does not exist; use `Select::relationship()`.
- **`Repeater` uses `->schema()`, not `->fields()`.**
- **Never nest a `Wizard` inside an action or create-option modal schema.** Use `->steps()` on the action, otherwise Enter submits the modal from the first step. See "Wizard in an action modal".
- **Never add `->dehydrated(false)` to fields that need to be saved.** It strips the value from form state before `->action()` or the save handler runs. Only use it for helper/UI-only fields.
- **Use correct property types when overriding `Page`, `Resource`, and `Widget` properties.** These properties have union types or modifiers that must be preserved:
  - `$navigationIcon`: `protected static string | BackedEnum | null` (not `?string`)
  - `$navigationGroup`: `protected static string | UnitEnum | null` (not `?string`)
  - `$view`: `protected string` (not `protected static string`) on `Page` and `Widget` classes
