---
id: US-24
title: "Issued Merchandise Log"
status: partial
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want a clear record of which merchandise items each member has already received — including complimentary gifts such as a first necker or a provided polo — so that I can apply trustee / oversight stash policy (full cover, replacement charge, or equivalent cost off another item) when they order again.

## Acceptance criteria

- Managers can record an issued item against a member without a shop order (e.g. a gifted necker), with item, date, and optional note.
- Handing over a fulfilled shop order line also writes an issued-item record ([US-87](US-87-merchandise-picking-and-handover.md)).
- Managers can review a member’s issued history (and a team-wide view filtered by item) when approving orders or answering questions such as “who already has a necker?” or “have they had their provided polo?”.
- The log does **not** automatically grant free items, allowances, or block orders; discounts stay manager discretion against external policy ([US-84](US-84-merchandise-order-approval.md)).
- Issued items are per team. Domain terms live in the [Merchandise & Stash domain note](merchandise-stash-domain.md).

## Decisions

- **Rename intent:** this is branded/personalised stash issuance, not booking equipment sets and not loaned session kit.
- **No auto entitlement:** policy (what is provided, what is charged, whether equivalent cost moves to another item) is set by the trustee board and the team manager’s manager. The log supports judgment; it does not encode the policy.
- **Bootstrap:** no historical necker list exists today; managers will seed the log as they confirm who already has items.
- **Anchor:** shop fulfilment is [US-36](US-36-merchandise-stash-requests.md); this story is the issuance history that outlives a single order.

## Progress

Partial. Managers can record gifts (item/variant, date, note) and review the team Issued list filtered by item or member when the Shop feature is on. Handover → issued from shop orders remains [US-87](US-87-merchandise-picking-and-handover.md).
