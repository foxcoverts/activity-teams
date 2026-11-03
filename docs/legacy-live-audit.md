# Legacy live site audit (climbfoxcoverts.co.uk)

Started: 2026-11-03. Findings appended during an authenticated Cursor browser walk so a mid-run stop still leaves a usable inventory.

**Method:** live UI walk vs Activity Teams stories / ROADMAP. Each item: what was seen → mapping → evidence.

**Legend**

- **done** — covered in Activity Teams for relaunch
- **deferred** — deliberately out of relaunch (ROADMAP)
- **extra** — story exists, not relaunch-required
- **retired** — legacy UI not required for relaunch (replacement noted)
- **gap** — user-facing legacy behaviour with no clear story / incomplete coverage for relaunch
- **parity note** — present in both, or documentation inconsistency only

---

## Progress

- [x] Dashboard / primary navigation inventory
- [x] Booking calendar and booking detail (+ share / related)
- [x] Keys / key transfer (list)
- [x] Documents
- [x] News
- [x] Incidents
- [x] Qualifications (own + manage + create/edit types)
- [x] Users directory / show / edit / create (after password confirm)
- [x] Kit checks (list)
- [x] Tasks (list)
- [x] Mail log / changes feed
- [x] Profile edit / notification settings / calendar links
- [x] Key transfer form
- [x] Footer / About Us
- [x] Summary of gaps vs Phase 0

---

## Dashboard / navigation

**URL:** `https://climbfoxcoverts.co.uk/dashboard`

**Dashboard body:** “Recent News” teaser (“Permits & Keys”), Read more, View all news. Footer: Privacy Policy, About Us, © Copyright 2026.

**Menu (opened):** Dashboard; My Rota; Invites; Documents; News; Report Incident; Calendar / Confirmed / Tentative / Cancelled / Add Booking; Changes; Keys; Kit Checks; Mail Log; Tasks; Qualifications; Users; Profile; Notification Settings; My Qualifications; Calendar Links; Logout.

---

## Kit checks

**URL:** `/kit-check` — Latest Kit Checks; “Log Kit Check”; people with last-check dates; links to per-user history.

**Mapping:** **deferred** — US-23 / US-33.

---

## Tasks

**URL:** `/todo` (menu “Tasks”; `/task` 404). Add Task; status filters; sample “Signage”; Complete task. Per-todo ICS exists in legacy routes.

**Mapping:** **deferred** — US-22 (incl. VTODO).

---

## Mail log

**URL:** `/mail` — list of inbound messages (To / From / Received).

**Mapping:** **retired** — general mail-log UI not required; US-55 inbound review + manager alerts.

---

## Changes (manager activity feed)

**URL:** `/change` — team-wide stream of attendance/response sentences (and comment excerpts further down the page).

**Mapping:** **extra** — US-38 (open). Per-booking history US-39 is **done**.

---

## Keys

**URL:** `/key` — ring list, holder, Add Key, Transfer Key per ring.

**Transfer** `/key/{id}/transfer` — “Transfer key to” member select (full team roster) + Transfer. No other fields.

**Mapping:** **done** (US-51 etc.). **parity note:** ROADMAP Phase 0 table still says US-51 not implemented; Phase 2 says done — fix the table.

---

## Documents

**URL:** `/document` — Upload; PDFs with notes and updated timestamps (e.g. Climbing & Abseiling Risk Assessment); Edit; one external POR link label “FS120430”.

**Mapping:** **done** — US-05. External reference links in notes are content, not a product gap.

---

## News

**URL:** `/news` — New; article list with author and “Posted … ago”; Read more. Dashboard embeds recent news.

**Mapping:** **done** — US-54.

---

## Incidents

**URL:** `/incident` — full report form (date/time, location defaults to campsite, injured, membership type, damage, details, reporter prefilled).

**Mapping:** **done** — US-21 (AT wizard + team select + email distribution).

---

## Bookings

**Calendar** `/booking` — month/day FullCalendar-style UI (Nov 2026 empty in this walk).  
**Lists** `/booking/confirmed` (and tentative/cancelled) — empty this season.  
**My Rota** `/rota`, **Invites** `/invite` — empty for this account right now.  
**Detail** `/booking/01m5764dz2jf5bnb3c8y68mmmg` — Edit, Related, Share, RSVP (Going), Guest list, Recent Activity; venue/group/activity copy.  
**Related** `…/related` — Add Related Booking.  
**Share** `…/share` — readonly share link + “Copy link”; “Share post” text + “Copy post”; “Open Facebook Group”.

**Mapping:**

- Core booking lifecycle / guest list / related / ICS: **done**
- Share copy UI (link + social post helpers): **extra / not-started** — US-29. Public booking URL / OG already **done** (US-12/30/31). Facebook Group deep-link is legacy-specific chrome, not a separate AT story.

---

## Calendar links

**URL:** `/booking/links` — Rota Link + Calendar Link (readonly), copy controls, Reset Links warning.

**Mapping:** **done** — US-13 (incl. regenerate/revoke).

---

## Account / profile / notifications

**Gate:** `/profile` and `/user` redirect to **Confirm Password** before access (session confirmed during this walk).

**Profile** `/profile` — Profile Information: Name, Email, Phone; Emergency Contact Name + Phone (helper: lead instructor may need these); Timezone. Separate **Update Password** block (Current / New / Confirm) with rules: ≥8 chars, upper+lower, symbol, number.

**Notification Settings** `/profile/notifications` — invite / change / confirmed / cancelled each Default(On) | On | Off; comments Default(All) | All | Replies | Lead Instructor | None.

**Mapping:**

- Profile / password / emergency contact: **done** (US-56). Password-confirm gate is legacy UX chrome — not a relaunch gap.
- Notification prefs: **done** as US-61, but legacy granularity (separate invite/change/confirm/cancel + comment audience modes) differs from AT’s Bookings / Keys / News catalog. **parity note** — product may accept the AT model; not an unplanned feature unless relaunch requires the old toggles 1:1.

---

## Users (members)

**List** `/user` — Add User; roster with role badges (Climbing Manager / Permit Holder / Key Holder etc. observed).

**Show** `/user/{id}` — badges (e.g. Team Leader, Permit Holder, Key Holder); Contact Details (email verified, email, phone) behind “legitimate reason” acknowledgement copy (UK GDPR notice); Emergency Contact (name/phone) same pattern; Kit Checks (date + Expired badge); Settings (Section e.g. Adult Member, Timezone); links Edit / Bookings / Qualifications.

**Edit** `/user/{id}/edit` — Name; Contact / Emergency / Settings sections; **Section** select: Squirrel, Beaver, Cub, Scout, Explorer, Network, Adult Member, Parent; **Role**: Team Leader, Team Member, Guest, Suspended; capability checkboxes: Kit Checker, Manage Bookings, Manage Qualifications, Manage Users; Timezone.

**Create** `/user/create` — Name, Email (copy: user sets own password), same Section/Role/capabilities/Timezone; default Role Guest, Section Adult Member.

**Mapping:** **done** — US-49/59 (members), membership role model in AT is Manager/Member/Parent + suspended + under_18 section collapse. **parity notes (accepted model shifts, not gaps):**

1. Legacy fine-grained section ages (Squirrel…Network) vs AT under_18 + Parent membership role.
2. Legacy Team Leader / capability checkboxes vs AT Manager + coarser permissions (kit-checker deferred with kit).
3. Contact-detail acknowledgement on show maps to US-58 behaviour; AT must keep that pattern on multi-person contact exposure.

---

## Qualifications

**Own / per-user list** `/profile/qualifications` and `/user/{id}/qualification` — Add; MT + Scout Permit cards with relative expiry.

**Manage list** `/qualification` — Show expired toggle; Add; all team quals with Type (Mountain Training / Scout Permit / Girlguiding), User, expiry relative copy. Live data still includes Girlguiding rows (some long-expired).

**Add flow** `/qualification/create` → pick user → `/user/{id}/qualification/create` — Qualification Type: Girlguiding | Mountain Training | Scout Permit (then type-specific fields).

**Scout Permit edit** — Activity (Climbing and Abseiling only), Category (Artificial/Natural Top Rope / Artificial/Natural Lead Climbing), Permit Type (Personal / Leadership / Supervisory), Restrictions text + “No restrictions”, Expires date, Save / Remove.

**Mountain Training edit** — Award only: ICA, CWI, CWI Abseil, CWDI, RCI, RCDI (no expiry on form).

**Mapping:** **done** — US-01/07. Girlguiding type still present in legacy UI/data; ROADMAP treats Girlguiding as out of relaunch / deferred content decision — **not a new gap** unless relaunch must keep editing Girlguiding rows. Confirm data migration choice for existing Girlguiding records separately from product UI.

---


## Footer / About Us

**Privacy Policy** → `/privacy-policy` (AT: `/privacy`) — **done**.  
**About Us** href is `/` (the public climbing welcome), not a separate `/about` page (`/about` 404s).

**Mapping:** **done** via public welcome at `/` (Fox Coverts Climbing advert). No separate About gap.

---

## Summary — relaunch inventory check (Phase 3 item 5)

### No new required relaunch gaps found in this walk

Everything in the primary nav maps to **done**, **deferred**, **extra**, or **retired** already recorded in ROADMAP/stories.

### Still open by prior scope (not newly discovered)

| Item | Scope |
| --- | --- |
| US-38 Changes / team-wide manager feed | extra |
| US-29 Share link / post copy helpers | extra |
| US-23/33 Kit checks | deferred |
| US-22 Tasks / VTODO | deferred |
| General Mail Log UI | retired → US-55 |
| US-16 nudges, US-45 bounce tracking, US-50/53 invite edges, US-62 unmapped replies | extra / not-started (not all exercised in this UI walk) |

### Doc / product notes only

1. Fix ROADMAP parity table row for US-51 (says not implemented; Phase 2 done).
2. Legacy notification settings are finer-grained than US-61; confirm acceptance of the AT model for relaunch (already implied by US-61 done).
3. Password-confirm gate on Profile/Users was observed; no separate story needed unless you want that exact UX.
4. Legacy member Section/Role/capability checkboxes vs AT membership model — accepted shift, not a gap.
5. Legacy still lists Girlguiding quals; decide migration/display for those rows (UI for new Girlguiding awards not required for relaunch).

### Not fully exercised (empty data / skip write actions)

- Log Kit Check form, Add Task form, booking create, deep mail-log message view, Add Key form.
- Booking lists/rota/invites empty for current season; detail taken from “Testing” booking via Changes.
- Did not submit create/edit/transfer (read-only walk).

**Conclusion for Phase 3 close-out:** this live pass supports ROADMAP’s claim that the retained workflow inventory is complete. Remaining nav items are already classified deferred/extra/retired. Closing Phase 3 is a product sign-off on that classification (plus optional doc tidy), not a new build slice from this audit.
