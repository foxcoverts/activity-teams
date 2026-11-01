---
id: US-51
title: "Pass a Key Ring"
status: done
area: qualifications
scope: required
---

## Story

As a member, I want to pass a key ring I hold to another member so that the keys on it go with them without being copied onto either person.

## Acceptance criteria

- A member who holds a key ring can pass it to another member.
- Passing changes that ring's holder and leaves its keys in place.
- A manager of a team that needs a key this ring opens can pass it, including when it is in the cupboard or held by someone outside the team.
- Recipients are current unsuspended members of those interested teams, including invitation placeholders, or the cupboard. A pass to the same holder or to the cupboard when it is already there is a no-op.
- The new holder, the previous holder when they are not the actor, and interested-team managers when the key returns to the cupboard get an inbox item (without double-sending the previous holder). The digest emails it when `keys.mail` allows, including a return to the cupboard. Placeholders, suspended people, and unverified or inactive accounts are not notified. Adopting a placeholder moves their keys silently. Admin catalog edits stay silent.

## Decisions

- **Who can pass:** the current holder, or a manager of a team that needs a key this ring opens (including a key a ring key covers). System admin catalog edit stays as a silent correction and does not send pass mail.
- **Who can receive:** current, unsuspended members of those interested teams, or the cupboard (`holder_id` null). Placeholders are included regardless of invitation status, matching booking attendance. Exclude self, deleted people, and suspended people. Cupboard is omitted when the ring is already there.
- **Placeholders cannot pass for themselves.** A manager of an interested team moves rings to or from them until someone adopts the placeholder. Adopting reassigns `holder_id` without pass mail.
- **What moves:** `key_rings.holder_id` only. Keys on `key_ring_key` stay put.
- **Mail skips the actor.** Holder to member: only the new holder. Manager to member: previous and new holder (not the manager, unless they are one of those people). Anyone to cupboard: previous holder if they are not the actor, and interested-team managers except the actor and except someone who already received the previous-holder notice. No notice on a no-op. Holder, previous-holder, and cupboard notices are inbox items and follow [US-61](US-61-notification-settings.md) `keys.mail` when the digest emails them.
- **Not in this slice:** pass from the booking Keys modal, ring timeline UI.

## Progress

A holder or an interested team's manager can pass a key from the member keys list and view, and from the manage keys list. Pass notices are inbox items and skip the actor. Holder, previous-holder, and cupboard-return notices follow `keys.mail` when the digest emails them.
