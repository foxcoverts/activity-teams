---
id: US-85
title: "Merchandise Invoices & Treasurer Handoff"
status: done
area: merchandise
scope: extra
---

## Story

As an Activity Manager, I want approved merchandise orders to lock what is owed, ask the treasurer to invoice the member, and let me mark payment received so that we track who still owes money without taking payments on the site.

## Acceptance criteria

- On approve, the order locks **amount due** from approved line amounts and order-level discount ([US-84](US-84-merchandise-order-approval.md)).
- When amount due is greater than zero, approve collects a treasurer email (prefilled from the team default when set) and queues a **request that the treasurer invoice the member** (To treasurer; Cc member and manager; Reply-To manager). Simple billable lines only — not a tax invoice from the site.
- When amount due is zero, the order is marked paid immediately; the member gets an approved / nothing-to-pay inbox notice; no treasurer mail.
- Managers can filter unpaid orders, mark paid when cash/cheque/transfer is in, and resend the treasurer request while unpaid.
- No member paid notice from the app (treasurer confirms receipt outside). No amount-due inbox notice when due > 0 (member is Cc’d on the treasurer request).
- The site does not process card or other online payments. There is no separate invoice entity or Invoices nav.
- Domain terms: [Merchandise & Stash domain note](merchandise-stash-domain.md). Parent: [US-36](US-36-merchandise-stash-requests.md).

## Decisions

- **Money on the order:** `amount_due` (default 0) and `paid_at`; payment status None / Unpaid / Paid. No `merchandise_invoices` table.
- **Request vs invoice:** the app asks the treasurer to raise their invoice; it does not send an invoice on their behalf.
- **Team `treasurer_email`:** optional Admin field, visible only when Shop is on (live). Prefills approve/resend.
- **Payment methods / credit notes / expire unpaid:** out of scope; treasurer owns real invoicing and receipt confirmation.
- **Outstanding triage widget:** still open with other merchandise widgets — see [domain note](merchandise-stash-domain.md#manager-triage-widgets-open).
- **“Invoice sent” chase state / treasurer login:** deferred until needed.

## Progress

Done for this slice. Approve locks amount due; £0 auto-paid with notice; due > 0 queues treasurer request and shows unpaid on Orders; mark paid and resend on the order view; optional team treasurer email.
