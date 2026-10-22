---
id: US-14
title: "Interactive Email RSVP Action Buttons"
status: done
area: communications
scope: required
---

## Story

As a Team Member, I want secure, one-click action links inside invitation emails to update my attendance instantly without logging into the full system.

## Acceptance criteria

- An invitation, or an update before they have answered, includes Yes, Maybe, and No. There is no separate Respond button.
- A Yes or No keeps the saved response and a View response link.
- A Maybe, while the booking still accepts responses, asks them to update it with Yes, Maybe, and No.
- A past or cancelled booking keeps View response only.
- Each link uses that person's attendance token and can change only their response.
- Opening a link does not save the answer. The page counts down and then submits, and they can confirm immediately or cancel. A fetch that does not run the page script does not record a response.
