---
id: US-37
title: "Related Bookings & Multi-Day Event Linking"
status: done
area: bookings
scope: required
---

## Story

As an Activity Manager, I want to link related bookings together (such as separate sessions across a multi-day weekend camp) so that related activities share a cohesive context and are easy for members and leaders to navigate collectively.

## Acceptance criteria

- Allows managers to associate one booking with another as a related event.
- Displays a visible link or section on booking detail views so users can quickly jump between connected sessions of a larger camp or event.
- Automatically maps linked bookings into standard iCalendar export structures using the RELATED-TO property (and RELTYPE parameters) so external calendar apps group related sessions logically.
- Related sessions keep separate email threads. The calendar UID stays `booking+{id}@{host}` for each booking. Email `References` is not used to group a camp.

## Progress

Done. Managers link same-team bookings symmetrically from the Teams booking view. The Related bookings panel shows when there are viewable links or when the manager can add at least one (future, not cancelled) candidate; otherwise it stays hidden. ICS export adds `RELATED-TO;RELTYPE=SIBLING`.
