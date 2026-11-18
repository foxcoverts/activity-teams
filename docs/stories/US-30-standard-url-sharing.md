---
id: US-30
title: "Standard URL Sharing Capability"
status: done
area: communications
scope: required
---

## Story

As an Activity Manager or Team Member, I want standard booking URLs to remain stable and shareable so that I can also just grab them straight from my browser address bar.

## Acceptance criteria

- Standard booking URLs remain fully functional and unauthenticated-friendly when shared externally.
- ICS `URL` / description links use the teams booking path, not a separate share route.
- A teams URL with an unknown team slug but a real booking/task/news/debrief/document id permanently redirects to that record’s owning team when the viewer can open it (guests: booking preview only). A real but wrong team slug stays a not-found miss so tenant isolation holds.
