---
id: US-36
title: "Merchandise & Stash Shop"
status: not-started
area: merchandise
scope: extra
---

## Story

As a team member, I want a team shop where I can browse stash and branded clothing and place an order so that I can request kit through the team without informal side channels.

As an Activity Manager, I want that shop backed by catalogue, approval, invoicing, supplier, and handover tools so that orders move from request to paid and received in one place.

## Acceptance criteria

- Members of a team can browse that team’s merchandise catalogue and submit orders ([US-82](US-82-merchandise-catalogue-and-stock.md), [US-83](US-83-merchandise-member-orders.md)).
- Managers are notified when a new order is submitted, and can approve or reject it with discretionary discounts ([US-84](US-84-merchandise-order-approval.md)).
- Approved orders produce invoices that managers can track and mark paid, and can email onward to the treasurer ([US-85](US-85-merchandise-invoices.md)).
- Managers can raise manufacturer orders and picking lists, record goods in, and hand items to members, including partial fulfilment ([US-86](US-86-merchandise-manufacturer-orders.md), [US-87](US-87-merchandise-picking-and-handover.md)).
- Handed-over and gifted items appear on the issued-items log ([US-24](US-24-equipment-merchandise-allocation.md)).
- Domain terms and deferred behaviour are defined in the [Merchandise & Stash domain note](merchandise-stash-domain.md).

## Decisions

- **Umbrella:** this story is the shop-facing anchor. Build work is sliced in US-82–US-87 and US-24; this file stays the map and high-level member outcome.
- **Audience:** all current members of the team may order. Catalogue and stock are per team; no cross-team shopping.
- **Not booking kit:** unrelated to equipment sets used on bookings.
- **Payments:** offline only; the site tracks invoice paid / unpaid.

## Progress

Not started. Domain and child stories documented; no application code yet.
