# Qualification family YAML

Use this file to draft or extend a qualification family. Return YAML only, plus a `families.yaml` stub. Do not invent PHP, database columns, icons, slugs, translation keys, extra payload fields, or Scout permit types.

Do not ask follow-up questions that invent covering or permit types. If the source does not say that one award covers another, omit `covers`. If a certificate stands alone, leave it as a node with no `covers`.

## Shape

Every family is the same tree:

- **groups** — isolated covering sets. Covering **never** crosses groups. Use more than one group only when the same `name` would mean different things in different contexts, or when two awards must not cover each other even if the names look related.
- **section** — optional Filament **optgroup** on a node (Walking, Climbing, Coaching). Headings, folders, and “kinds of award” are sections, **not** groups. Covering can cross sections inside one group.
- **categories** — named nodes inside a group (permit categories, award names, levels).
- **covers** — *direct* edges only, and only when the awarding body says the higher award counts as the lower one. The app expands grandparents. A node always covers itself; do not list self.

**Default for a new awarding body:** one group, `section` for UI headings, no `group_key`, `type_dimension: false`, no `types_allowed`. That is the Mountain Training shape.

## Groups vs sections

| Use | When |
| --- | --- |
| `section: Coaching` on each node | UI heading only. Names are unique. Covering may later link across headings. |
| A second `groups` entry | Isolation. Canoeing River `B1` is not Hovercrafting Water `B1`. Covering must not cross. |

If you are unsure, use **one group and sections**. Splitting into groups cannot be undone by covering.

## Permit types

`personal`, `leadership`, and `supervisory` exist only on **Scout permits**.

- Default `type_dimension: false`.
- Do **not** output `types_allowed` unless `type_dimension` is true **and** the source says which of those three exist on that node.
- Do not guess leadership/supervisory from “coach”, “instructor”, or “range officer”.
- Do not add a fourth permit type.

When `type_dimension` is true, omit `types_allowed` if every type is allowed. Leadership does not cover Personal. Supervisory covers Leadership only.

## families.yaml stub

Copy the award-family stub unless the source is clearly Scout-style permits or Girlguiding-style scheme + level.

```yaml
- id: example_awards          # snake_case; becomes the stored kind
  label: Example awards       # form Type select
  short_label: Example        # optional; summaries and list Type column (defaults to label)
  expires: true               # false → no expiry field; stored expires_on is null
  restrictions: false         # true → free-text restrictions on the person record
  unique: true                # true → at most one of each node per person
  type_dimension: false       # true only for Scout-style personal/leadership/supervisory
  node_key: award             # payload key: award | category | level
  file: example-awards.yaml
```

Add `group_key` only when there is a group select (Scout `activity`, Girlguiding `scheme`). Omit it when there is a single group.

Do not reuse `scheme` / `level` unless the family is actually schemes with numbered levels. Named certificates and coaching awards use `node_key: award`.

## Family file template (one group + sections)

```yaml
groups:
  - name: Example awards
    categories:
      - name: Root award
        section: Coaching          # optgroup only; omit covers on roots
      - name: Higher award
        section: Coaching
        covers:
          - Root award             # direct child only; omit if the source does not say it covers
      - name: Standalone certificate
        section: Range             # no covers — do not force it into the coaching stack
```

`name` values are stored as-is. They must be unique inside a group. Use the awarding body's public name, not a code (`Indoor Climbing Assistant`, not `ICA`).

## Isolated groups (only when names collide or covering must not cross)

```yaml
groups:
  - name: Hill walking
    categories:
      - name: Lowland
      - name: Moorland
        covers:
          - Lowland
      - name: Mountain
        covers:
          - Moorland
  - name: Winter
    categories:
      - name: Winter mountain
```

If Winter mountain should cover Hill walking Mountain, this family needs **one** group (names unique in the file), not two. Do not invent that covering to “join” groups.

## Rules

1. `covers` lists only names that exist in the **same group**. Never copy a list from another group.
2. Do not repeat grandparents. If A covers B and B covers C, write `A: [B]` and `B: [C]`, not `A: [B, C]`.
3. Do not add `prerequisites`, aliases, icons, or ids. One `name` per node.
4. Omit empty `covers: []`. Omit `types_allowed` entirely when `type_dimension` is false.
5. Do not mix two awarding bodies in one file. One family per file.
6. Do not invent `covers` to make a tidy ladder. A tutor, range officer, or similar may sit beside a coaching stack with no edge between them.

## Self-check

- Headings such as Coaching, Range, Officiating are `section` values, not `groups`.
- `type_dimension` is false, and no `types_allowed` keys exist, unless the source is Scout-style permits.
- `group_key` / `node_key` match the family (do not copy `scheme` / `level` onto an award list).
- Every `covers` entry exists in the same group.
- No duplicate `name` inside a group.
- No cycles (A covers B covers A).
- No grandparent names in `covers`.
- `id` in the stub is snake_case and the `file` name matches the YAML you return.


To begin, acknowledge these instructions and ask me to describe the new qualification.