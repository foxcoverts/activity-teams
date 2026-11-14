---
id: US-84
title: "Merchandise Order Approval & Discounts"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want to approve or reject member merchandise orders and apply discretionary discounts so that trustee / oversight stash policy (provided items, replacements, or equivalent cost off another line) is reflected before an invoice is raised.

## Acceptance criteria

- Managers of the team can approve an order or reject it with a reason (including when feedback from a manufacturer means the order cannot proceed).
- On approve, managers can adjust line amounts (full or partial discount) at their discretion; there are no fixed subsidy rules in this story.
- Managers can consult the issued-items log when deciding discounts ([US-24](US-24-equipment-merchandise-allocation.md)).
- Approval notifies the member; rejection notifies the member with the reason.
- Approving an order creates or updates the member invoice for that order ([US-85](US-85-merchandise-invoices.md)).
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Discretion only:** managers apply external policy at approval (e.g. 100% off a provided polo, or reduce a hoody by that equivalent). Fixed in-app rules, allowance balances, and discount codes are deferred.
- **Past issues matter:** approval should make it easy to open the member’s issued history ([US-24](US-24-equipment-merchandise-allocation.md)) while setting discounts.
- **Manufacturer reject:** can be recorded as a rejection (with reason) after the order was already in flight; exact state machine is a build choice so long as the member sees a clear outcome.
- **System admin:** does not imply team manager; approval requires `manages($team)` (same pattern as other team ops).

## Progress

Not started.
