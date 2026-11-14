---
id: US-82
title: "Merchandise Catalogue & Stock"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to maintain a per-team catalogue of stash items — with manufacturer, sizes, prices, images, stock or made-to-order, availability, and optional personalisation — so that members order from a clear list, I can mothball old items without breaking history, and supplier orders can be grouped correctly.

## Acceptance criteria

- Managers can maintain a controlled per-team list of manufacturers and associate each catalogue item with a manufacturer.
- Managers can create and edit catalogue items for their team: title, description, image, manufacturer, fulfilment type (held in stock vs made to order), availability, and one or more sizes each with a cost.
- An item may define optional personalisation fields (e.g. name, colour, other text) that appear on the order form ([US-83](US-83-merchandise-member-orders.md)).
- Stock items expose a simple quantity per size; the count decreases when stock is issued ([US-87](US-87-merchandise-picking-and-handover.md)), and managers can edit the number manually.
- Made-to-order items do not require an on-hand quantity to be orderable.
- Managers can mothball (or otherwise mark unavailable) an item so members can no longer order it, without deleting it; existing orders, invoices, and issued records remain intact. Managers can re-activate an item.
- Members only browse and order **available** catalogue items for teams they belong to; there is no cross-team catalogue.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Manufacturer list:** per-team controlled records, not free-text on the item alone, so [US-86](US-86-merchandise-manufacturer-orders.md) can group lines by manufacturer reliably.
- **Availability:** mothball rather than delete; prefer soft availability over hard delete so history stays linked to the catalogue item.
- **Batch windows:** campaign deadlines (e.g. a month to assemble a supplier order) stay informal; availability and mothballing are the catalogue levers, not an automated campaign rule.
- **Not in this slice:** fancy stocktake/write-off workflows, member returns, discount codes.

## Progress

Not started.
