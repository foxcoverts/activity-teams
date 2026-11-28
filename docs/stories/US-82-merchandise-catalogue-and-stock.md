---
id: US-82
title: "Merchandise Catalogue & Stock"
status: partial
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to maintain a per-team catalogue of stash items — with supplier, sizes, prices, images, on-hand stock, availability, and optional personalisation — so that members order from a clear list, I can mothball old items without breaking history, and supplier orders can be grouped correctly.

## Acceptance criteria

- Managers can maintain a controlled per-team list of **suppliers** (name + free-form markdown notes) and optionally associate each catalogue item with a supplier.
- Managers can create and edit catalogue items for their team: title, description, image, optional supplier, optional supplier code (product/catalogue reference), availability, and one or more variants (SKUs) each with a cost and on-hand stock quantity (default 0).
- An item may define optional **option axes** (e.g. Size, Colour) with closed values; each value may have an optional short note shown in shop/SKU dropdowns (not part of the composed SKU label). Each variant binds to one value per axis (sparse combinations allowed). The shop resolves member picks to a single variant via cascading selects.
- An item may define optional personalisation fields (e.g. name on shirt) that appear on the order form ([US-83](US-83-merchandise-member-orders.md)). Closed-list personalisation is out of scope until a non-SKU preference needs it.
- Every variant has a simple on-hand quantity; the count decreases when stock is issued ([US-87](US-87-merchandise-picking-and-handover.md)), and managers can edit the number manually (including sofa finds / returns). There is **no** separate fulfilment-type flag — shortage (including stock 0) feeds the same supplier-order triage as [US-86](US-86-merchandise-manufacturer-orders.md).
- Managers can mothball (or otherwise mark unavailable) an item so members can no longer order it, without deleting it; existing orders, invoices, and issued records remain intact. Managers can re-activate an item.
- Members only browse and order **available** catalogue items for teams they belong to; there is no cross-team catalogue.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Supplier list:** per-team controlled records (`MerchandiseSupplier`), not free-text on the item alone, so [US-86](US-86-merchandise-manufacturer-orders.md) can group lines by supplier reliably. Name + markdown notes only; no supplier availability lifecycle. Delete blocked while items still link; rename / `(DNU)` or clear item links first.
- **No fulfilment type:** stock qty on every variant (often 0). Goods-in for member demand vs cupboard restock is a [US-86](US-86-merchandise-manufacturer-orders.md) concern, not a catalogue flag.
- **Stock UI:** counts edited on the item **Stock** tab (not the Variants SKU setup tab).
- **Availability:** mothball rather than delete; prefer soft availability over hard delete so history stays linked to the catalogue item.
- **Batch windows:** campaign deadlines stay informal; availability and mothballing are the catalogue levers, not an automated campaign rule.
- **Not in this slice:** fancy stocktake/write-off workflows, member returns as a workflow, discount codes, team-wide Stock list page.

## Progress

Partial. Team Shop feature flag; Merchandise nav **Issued**, **Items**, **Orders**, **Shop**, **Suppliers**. Items tabbed create/edit (**Details** / **Variants** / **Stock** on edit / **Personalisation**): title, description, optional searchable supplier, optional supplier code, availability (**draft / available / mothballed**), image gallery, option axes (values with optional dropdown notes) + SKUs, Stock tab for on-hand qty (default 0), personalisation fields. Items table supplier filter lists all team suppliers (unused disabled) plus **None**. Still open: stock decrement on pick ([US-87](US-87-merchandise-picking-and-handover.md)), per-variant images / colour swatches, closed-list personalisation.
