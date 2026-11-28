---
id: US-86
title: "Supplier Orders & Goods In"
status: done
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to generate a supplier order per supplier from approved lines that need buying, and to check items off as they arrive in batches, so that I can send a clear order outward and track partial deliveries.

## Acceptance criteria

- Managers can build a supplier order for one supplier (from the team’s controlled list) from approved lines associated with that supplier ([US-82](US-82-merchandise-catalogue-and-stock.md)). Cupboard top-up beyond member demand is ordered with the supplier outside the app; the app does not model restock qty on the supplier order.
- The supplier order can be exported on-screen and as CSV (same columns) for emailing as-is, editing outside the app, or retyping into a supplier portal.
- Once lines are included on a sent supplier order, members can no longer amend those lines ([US-83](US-83-merchandise-member-orders.md)).
- Managers can record goods in against supplier order lines in partial batches across multiple deliveries and multiple supplier orders.
- **Goods in:** member-demand lines check off as arrived / ready to pick (**no** stock bump). Extra cupboard stock is corrected on the Items Stock tab.
- Goods in makes demand lines available for handover to members ([US-87](US-87-merchandise-picking-and-handover.md)).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Batch windows:** ordering deadlines stay informal (including a month-long campaign). Managers gather approved lines, then send one supplier order; no campaign entity in this story.
- **Supplier reject:** if the supplier cannot fulfil, managers reject or adjust the affected member lines with a reason ([US-84](US-84-merchandise-order-approval.md)).
- **Grouping:** lines group by the catalogue item’s supplier association, not ad-hoc typing at order time.
- **Line link:** each member order line has at most one `merchandise_supplier_order_id` (whole-line attachment). One open draft per team+supplier.
- **No cupboard reorder engine:** managers may buy more than demand when emailing the supplier; the app only tracks which member lines are on the order.
- **Unified pipeline:** no catalogue fulfilment type. Pick what on-hand covers ([US-87](US-87-merchandise-picking-and-handover.md)); supplier-order the rest.
- **Goods in:** whole-line `arrived_at` check-off on sent orders (undo allowed until handover consumes ready-to-pick). No partial qty within a line in this story.
- **Nav:** Merchandise **Supplier orders** is good enough for this slice; expect a US-36 IA revisit once picking sits with goods-in.

## Progress

Done. Draft board (supplier picker, draft vs past orders, checkbox in/out, select all with no on-hand, qty/on-hand hint), send, on-screen export table + CSV (`order-{supplier}-{sent_at}.csv`, label-union columns), whole-line goods in on the sent board (picker shows `arrived/total` or `complete`). File name still says manufacturer for link stability; product language is **supplier**.
