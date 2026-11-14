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

- Approving an order creates (or updates) an invoice for the member reflecting approved line amounts after discounts ([US-84](US-84-merchandise-order-approval.md)).
- Managers can list outstanding (unpaid) invoices for the team and mark an invoice paid when cash, cheque, or bank transfer has been received.
- Managers can email a copy of an invoice (or a summary suitable for the treasurer) to an address they choose; there is no in-app treasurer role.
- The member is notified when an invoice is raised and when it is marked paid.
- The site does not process card or other online payments.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Payment methods:** irrelevant to the site beyond “paid” vs “unpaid”.
- **Partial payment / credit notes:** owned by the external treasurer; not a full ledger in-app. A short manager note on an invoice may be added later if needed.
- **Expire unpaid invoices:** not required for the first build; managers can chase or reject/cancel via order lifecycle if needed.

## Progress

Not started.
