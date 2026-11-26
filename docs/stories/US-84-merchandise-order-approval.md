---
id: US-84
title: "Merchandise Order Approval & Discounts"
status: partial
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to approve or reject member merchandise orders and apply discretionary discounts so that trustee / oversight stash policy (provided items, replacements, or equivalent cost off another line) is reflected before an invoice is raised.

## Acceptance criteria

- Managers of the team can approve an order or reject it with a reason (including when feedback from a manufacturer means the order cannot proceed).
- On approve, managers can adjust line amounts (full or partial discount) at their discretion; there are no fixed subsidy rules in this story.
- Managers can also apply an **order-level value discount** when the credit does not belong to a single line.
- Managers can consult the issued-items log when deciding discounts ([US-24](US-24-equipment-merchandise-allocation.md)).
- Approval notifies the member; rejection notifies the member with the reason.
- Approved money (line approved unit prices, order discount, total due) is stored on the order; creating the member invoice is [US-85](US-85-merchandise-invoices.md).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Discretion only:** managers apply external policy at approval (e.g. 100% off a provided polo, or reduce a hoody by that equivalent). Fixed in-app rules, allowance balances, and discount codes are deferred.
- **Line and order discounts:** per-line approved unit price (set price or enter £ discount off list — same result) plus an optional order-level £ off the items subtotal. Order discount is not auto-allocated across lines.
- **No partial line-reject:** if personalisation or availability is wrong, reject the whole order with a clear reason. Recovery path is reopen into the basket ([US-83](US-83-merchandise-member-orders.md)), not editing lines in place on approve.
- **Rejected ≠ Cancelled:** manager rejection is its own status; member cancel stays `Cancelled`.
- **Past issues matter:** approval should make it easy to open the member’s issued history ([US-24](US-24-equipment-merchandise-allocation.md)) while setting discounts.
- **Manufacturer reject:** can be recorded as a rejection (with reason) after the order was already in flight; exact later state machine is a build choice so long as the member sees a clear outcome.
- **System admin:** does not imply team manager; approval requires `manages($team)` (same pattern as other team ops).
- **Manager in-place edit** of variant / personalisation / qty is deferred; use member amend or reject + reopen until those prove clumsy.
- **Triage widgets:** managers will need a pending-approval queue (and related merchandise attention). Home is undecided — Manager dashboard vs a new Merchandise dashboard — see [domain note](merchandise-stash-domain.md#manager-triage-widgets-open).

## Progress

Partial. Managers can set per-line prices/discounts and an order-level discount on the Items table while pending, approve (optional note) or reject (required reason), open Issued history for the member, and the member gets an inbox notice. `Rejected` is distinct from `Cancelled`. Approved totals live on the order; invoice create is US-85. Still open: reopen cancelled/rejected into basket; any later manager line-detail edit; merchandise triage widgets (placement TBD).
