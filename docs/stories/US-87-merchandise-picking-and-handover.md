---
id: US-87
title: "Merchandise Picking & Member Handover"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want a picking list for stock items and a way to mark ordered (or gifted) items handed to members — including partial handovers across meetings — so that we know what is still outstanding without tracking postage formally.

## Acceptance criteria

- Managers can generate a picking list for approved lines fulfilled from on-hand stock.
- Picking / issuing from stock reduces the simple stock quantity on that size ([US-82](US-82-merchandise-catalogue-and-stock.md)).
- Managers can mark order lines (or quantities on a line) as handed over to the member, including partial handovers over time and across deliveries from [US-86](US-86-merchandise-manufacturer-orders.md).
- Handover writes an issued-item record ([US-24](US-24-equipment-merchandise-allocation.md)).
- Members are notified when items are ready for handover and when they have been handed over.
- Collection versus post is not modelled beyond the handed-over flag.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Reservation:** prefer reserving at pick; at team scale, shortage may simply become a backorder.
- **Cutoff for stock lines:** once handed over, the member cannot amend or cancel that line ([US-83](US-83-merchandise-member-orders.md)).
- **Returns:** out of scope.

## Progress

Not started.
