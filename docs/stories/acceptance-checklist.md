# End-to-end acceptance checklist

Personal walkthrough for the product owner on the **live** site (`https://climbfoxcoverts.co.uk/`). This is **not** a GitHub Project board item — tick here (or copy elsewhere) as you go. Story files and this checklist are the source of truth for acceptance.

Most members are not yet using Activity Teams day-to-day; use that quiet window for this pass. **Ready for wider member use** when this checklist is complete (or residual risks are explicitly accepted), no open defects block retained required workflows, and you are willing to point the team at Activity Teams as the day-to-day system.

Cutover items already done (do not re-check as blockers):
- [x] Legacy import / live data on IONOS
- [x] Site live on the real domain with deploy from `main`
- [x] Outgoing mail
- [x] Queue worker via crontab (`queue:work` + `schedule:run`)

## High-risk behaviours

- [ ] **Cross-team isolation (US-47):** permitted and denied reads/writes across teams; qualifications stay person-owned with the agreed manager/oversight rules
- [ ] **Personal-data viewing (US-58):** another person's details stay hidden until acknowledgment; self-view exempt; acknowledgment never bypasses authorization
- [ ] **Contact windows (US-18/19):** phone and emergency windows in the booking timezone; lead vs attendee visibility; denial without US-58 / wrong role
- [ ] **Suspension (US-48):** suspended member loses access and optional notifications; manager exceptions still work and stay silent
- [ ] **Account and data rights (US-57):** invitation-only signup, password change/reset, export, confirmed deletion, privacy notice, last-admin refusal, 30-day contact purge with named shell
- [ ] **Inbound email (US-55):** ForwardEmail site aliases (`booking@`, `task@`, `key@`, `teams@` — each covers its `+` form) to `/api/webhooks/forwardemail/mail`; `team@` stays a human forward; known iMIP REPLY/REFRESH; unrouted/failed stay in admin queue; manager alerts without raw body; unauthenticated webhook rejected and not stored
- [ ] **Outbound bounce tracking (US-45):** ForwardEmail bounce webhook to `/api/webhooks/forwardemail/bounces`; hard bounce flags the member and notifies managers with a profile link; soft bounce logs only; changing email clears the flag
- [ ] **News (US-54):** manager publish; team feed isolation; digest to opted-in only; opt-out keeps feed access
- [ ] **Calendar feeds (US-13):** token access; regenerate/revoke stops the old URL; ICS content looks right (timezone, location, related siblings as applicable)
- [ ] **Booking calendar / iMIP (US-15 / US-76):** invite loads in Gmail/calendar; REPLY updates attendance; force-send / Send to Email when needed

## Live verification (Phase 4)

- [ ] Critical feature tests green locally / CI (isolation, suspension, consent windows, booking mail/calendar)
- [ ] Manual check of main Livewire/Filament flows on the devices/browsers the team uses
- [ ] Scheduled jobs fire as expected (`schedule:run` + queue drain)
- [ ] Calendar feeds and booking ICS in the live environment
- [ ] Monitoring, backups, and a known rollback path noted
- [ ] Spot-check imported memberships, bookings, attendance, qualifications, keys, and contact fields
- [ ] End-to-end pass against the retained legacy workflow inventory
- [ ] Accepted differences from climbing-team recorded (if any)
- [ ] Comfortable pointing the wider team at Activity Teams as day-to-day

## Notes

_Use this space for findings during the walkthrough._
