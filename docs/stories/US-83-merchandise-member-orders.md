---
id: US-83
title: "Merchandise Member Orders"
status: partial
area: merchandise
scope: extra
---

## Story

As a team member, I want to place and, when still allowed, amend or cancel an order for catalogue items — including size and any personalisation — so that the team receives a clear request without chasing messages.

## Acceptance criteria

- Any current member of the team can browse that team’s **available** catalogue items and submit an order with line items (item, size, quantity, personalisation when required). Mothballed items are not offered for new orders ([US-82](US-82-merchandise-catalogue-and-stock.md)).
- Submitting an order notifies relevant managers ([US-36](US-36-merchandise-stash-requests.md) notification moments).
- The member can amend or cancel an order (or its lines) until those lines have been sent on a manufacturer order, or until handed over when fulfilled from stock ([US-86](US-86-merchandise-manufacturer-orders.md), [US-87](US-87-merchandise-picking-and-handover.md)).
- The member can see the status of their own orders (submitted, approved, rejected, invoiced, awaiting handover, complete, etc.).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Approval: [US-84](US-84-merchandise-order-approval.md).

## Decisions

- **Who may order:** all current team members; not restricted to managers or leads.
- **Campaign and ad-hoc:** same order flow. A month-long push to assemble a large supplier order is just many member orders left open until managers send the manufacturer order; one-off requests use the same path ([merchandise-stash-domain.md](merchandise-stash-domain.md) Ordering patterns).
- **Cutoff:** changes stop once a line is on a sent manufacturer order, or once it is handed over from stock — whichever applies to that line.
- **Collection vs post:** informal; this story does not require postage or address fields.

## Progress

Partial. Teams Merchandise **Shop** browse; **Basket** (add from product view with variant, qty, personalisation; edit qty / remove; submit); **Orders** list/view with cancel while submitted; managers get an in-app notice on submit. Basket submit confirm is soft next-step copy (manager review + cancel-while-waiting) — revisit when amend-after-submit and richer statuses land. Still open: amend after submit, richer statuses (approved / invoiced / handover), optional mail channel for shop notices.
