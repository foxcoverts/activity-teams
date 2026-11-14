---
id: US-86
title: "Manufacturer Orders & Goods In"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to generate a manufacturer order per supplier from approved made-to-order lines, and to check items off as they arrive in batches, so that I can send a clear order outward and track partial deliveries.

## Acceptance criteria

- Managers can build a manufacturer order for one manufacturer (from the team’s controlled list) from approved lines associated with that manufacturer that need supplier fulfilment (and optionally stock replenishment) ([US-82](US-82-merchandise-catalogue-and-stock.md)).
- The manufacturer order can be exported or copied in a form suitable to send outside the app (email/PDF/CSV — exact format at build time).
- Once lines are included on a sent manufacturer order, members can no longer amend or cancel those lines ([US-83](US-83-merchandise-member-orders.md)).
- Managers can record goods in against manufacturer order lines in partial batches across multiple deliveries and multiple manufacturer orders.
- Goods in makes items available for handover to members ([US-87](US-87-merchandise-picking-and-handover.md)).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Batch windows:** ordering deadlines stay informal (including a month-long campaign). Managers gather approved lines, then send one manufacturer order; no campaign entity in this story.
- **Manufacturer reject:** if the supplier cannot fulfil, managers reject or adjust the affected member lines with a reason ([US-84](US-84-merchandise-order-approval.md)).
- **Grouping:** lines group by the catalogue item’s manufacturer association, not ad-hoc typing at order time.
- **Stock path:** in-stock fulfilment uses picking lists in [US-87](US-87-merchandise-picking-and-handover.md), not a manufacturer order, unless the manager is restocking.

## Progress

Not started.
