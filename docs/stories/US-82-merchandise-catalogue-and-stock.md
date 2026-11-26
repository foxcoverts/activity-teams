---
id: US-82
title: "Merchandise Catalogue & Stock"
status: partial
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to maintain a per-team catalogue of stash items — with manufacturer, sizes, prices, images, stock or made-to-order, availability, and optional personalisation — so that members order from a clear list, I can mothball old items without breaking history, and supplier orders can be grouped correctly.

## Acceptance criteria

- Managers can maintain a controlled per-team list of manufacturers and associate each catalogue item with a manufacturer.
- Managers can create and edit catalogue items for their team: title, description, image, manufacturer, fulfilment type (held in stock vs made to order), availability, and one or more variants (SKUs) each with a cost.
- An item may define optional **option axes** (e.g. Size, Colour) with closed values; each variant binds to one value per axis (sparse combinations allowed). The shop resolves member picks to a single variant via cascading selects.
- An item may define optional personalisation fields (e.g. name on shirt) that appear on the order form ([US-83](US-83-merchandise-member-orders.md)). Closed-list personalisation is out of scope until a non-SKU preference needs it.
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

Partial. Team Shop feature flag; Merchandise nav **Items** with tabbed create/edit (**Details** / **Variants** / **Personalisation**): title, description, availability (**draft / available / mothballed**), image gallery (required when available; uploads normalised to max 1600px with a 600px square card thumb), optional **option axes** with values and axis-aware SKUs (composed labels; generate missing combinations), free-text **personalisation** field definitions (label + required). Shop product view cascades axis selects to a variant (zero-axis items keep the flat variant select). Item↔variant rules: available needs ≥1 available variant; mothballed needs ≥1 available or mothballed variant. Still open: manufacturers, stock, fulfilment type, per-variant images / colour swatches, closed-list personalisation.
