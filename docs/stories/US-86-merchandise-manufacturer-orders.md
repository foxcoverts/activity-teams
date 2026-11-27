---
id: US-86
title: "Supplier Orders & Goods In"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to generate a supplier order per supplier from approved lines that need buying (shortage and/or restock), and to check items off as they arrive in batches, so that I can send a clear order outward and track partial deliveries.

## Acceptance criteria

- Managers can build a supplier order for one supplier (from the team’s controlled list) from approved lines associated with that supplier that are not covered by on-hand stock (and optionally extra restock qty) ([US-82](US-82-merchandise-catalogue-and-stock.md)).
- The supplier order can be exported or copied in a form suitable to send outside the app (email/PDF/CSV — exact format at build time).
- Once lines are included on a sent supplier order, members can no longer amend or cancel those lines ([US-83](US-83-merchandise-member-orders.md)).
- Managers can record goods in against supplier order lines in partial batches across multiple deliveries and multiple supplier orders.
- **Goods in split:** member-demand qty checks off as arrived / ready to pick (**no** stock bump); cupboard restock qty **does** increase on-hand stock ([US-82](US-82-merchandise-catalogue-and-stock.md)).
- Goods in makes demand lines available for handover to members ([US-87](US-87-merchandise-picking-and-handover.md)).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Batch windows:** ordering deadlines stay informal (including a month-long campaign). Managers gather approved shortage lines, then send one supplier order; no campaign entity in this story.
- **Supplier reject:** if the supplier cannot fulfil, managers reject or adjust the affected member lines with a reason ([US-84](US-84-merchandise-order-approval.md)).
- **Grouping:** lines group by the catalogue item’s supplier association, not ad-hoc typing at order time.
- **Unified pipeline:** no catalogue fulfilment type. Pick what on-hand covers ([US-87](US-87-merchandise-picking-and-handover.md)); supplier-order the rest (and optional restock).

## Progress

Not started. File name still says manufacturer for link stability; product language is **supplier**.
