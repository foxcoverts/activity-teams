---
id: US-85
title: "Merchandise Invoices & Treasurer Handoff"
status: not-started
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want approved merchandise orders to produce member invoices I can mark paid and pass to the treasurer by email so that we track who still owes money without taking payments on the site.

## Acceptance criteria

- An invoice is created (or updated) from an **approved** order’s total due (approved line amounts after line and order-level discounts from [US-84](US-84-merchandise-order-approval.md)).
- Managers can list outstanding (unpaid) invoices for the team and mark an invoice paid when cash, cheque, or bank transfer has been received.
- Managers can email a copy of an invoice (or a summary suitable for the treasurer) to an address they choose; there is no in-app treasurer role.
- The member is notified when an invoice is raised and when it is marked paid.
- The site does not process card or other online payments.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Payment methods:** irrelevant to the site beyond “paid” vs “unpaid”.
- **Partial payment / credit notes:** owned by the external treasurer; not a full accounts ledger in-app. A short manager note on an invoice may be added later if needed.
- **Balancing a prior credit without a ledger:** e.g. member paid, manufacturer could not fulfil, they submit a new order — managers use [US-84](US-84-merchandise-order-approval.md) order-level discount to balance the credit. This story stays centred on unpaid/paid, not credit balances.
- **Expire unpaid invoices:** not required for the first build; managers can chase or reject/cancel via order lifecycle if needed.
- **Outstanding-invoice triage widget:** expected with other merchandise attention widgets; dashboard home undecided — see [domain note](merchandise-stash-domain.md#manager-triage-widgets-open).

## Progress

Not started. US-84 stores approved money on the order; this story adds the invoice model, outstanding list, mark paid, and treasurer email. Triage widget placement follows the open merchandise dashboard decision.
