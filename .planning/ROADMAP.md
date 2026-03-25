# Roadmap: meintechblog Affiliate Cards

## Overview

Four phases port the Node-RED Amazon affiliate bot into the existing WordPress plugin. Phase 1 builds the complete Telegram webhook pipeline with settings foundation — the entry point for all new data. Phase 2 creates the persistent product library that stores everything Phase 1 receives. Phase 3 extends the server-side token processor to resolve `amazon:last` tokens before saving. Phase 4 delivers the browser-facing editor enhancements (dropdown picker, live tokens) and the admin product page. Every phase delivers a coherent, independently verifiable capability.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Settings + Telegram Webhook Pipeline** - Bot credentials in settings; full end-to-end Telegram message processing with affiliate link reply
- [x] **Phase 2: Product Library + Tracking-ID Registry** - Custom DB tables persisting products and tracking IDs; REST endpoints; backfill (completed 2026-03-25)
- [x] **Phase 3: Token Extension** - Server-side `amazon:last` / `amazon:heute` / `amazon:gestern` pre-pass in Post Processor (completed 2026-03-25)
- [ ] **Phase 4: Editor Enhancements + Admin Page** - Dropdown picker in Gutenberg block; live editor tokens; WP_List_Table admin page

## Phase Details

### Phase 1: Settings + Telegram Webhook Pipeline
**Goal**: The bot accepts Telegram messages, resolves Amazon links, and replies with a ready-to-use affiliate URL
**Depends on**: Nothing (first phase)
**Requirements**: SETT-01, SETT-02, SETT-03, TGBOT-01, TGBOT-02, TGBOT-03, TGBOT-04, TGBOT-05, TGBOT-06, TGBOT-07, TRID-03, TRID-04
**Success Criteria** (what must be TRUE):
  1. Admin can enter Bot Token, Chat ID, and Webhook Secret in Plugin Settings and save them
  2. Settings page shows whether the webhook endpoint is reachable (active/inactive indicator)
  3. Sending an `amzn.to` short link to the bot triggers a reply with a full affiliate URL containing the current tracking ID
  4. Sending a bare ASIN or a full Amazon product URL to the bot triggers the same affiliate URL reply
  5. Sending a date string (`heute`, `YYMMDD`, `DD.MM.YY`) or `reset` changes the tracking ID used in subsequent replies; messages from unauthorized chat IDs are silently ignored
  6. Bot warnt per Telegram wenn fuer das Post-Datum keine Tracking-ID in der Registry hinterlegt ist
  7. User kann per Telegram-Antwort (done/ok/angelegt) eine neue Tracking-ID als verfuegbar markieren
**Plans:** 2/3 plans executed
Plans:
- [x] 01-01-PLAN.md — Foundation: Settings extension + Tracking Registry + URL Resolver
- [ ] 01-02-PLAN.md — Core: Telegram Handler dispatch logic + webhook endpoint + plugin wiring
- [x] 01-03-PLAN.md — UI: Telegram Bot settings tab + webhook status check
**UI hint**: yes

### Phase 2: Product Library + Tracking-ID Registry
**Goal**: Every product received via Telegram is persisted in a queryable database table and exposed via REST API; all available tracking IDs are stored and backfilled
**Depends on**: Phase 1
**Requirements**: PLIB-01, PLIB-02, PLIB-03, TRID-01, TRID-02
**Success Criteria** (what must be TRUE):
  1. After a Telegram message is processed, a row appears in `{prefix}mtb_affiliate_products` with ASIN, title, detail URL, image URL, and received timestamp
  2. `GET /wp-json/mtb-affiliate-cards/v1/products` returns all stored products sorted by date descending
  3. `GET /wp-json/mtb-affiliate-cards/v1/products/last` returns the most-recently-received product (and `products/last2` returns the second-most-recent)
  4. Plugin activation on a fresh site creates the tables without manual SQL; re-activation on an existing site does not destroy data
  5. Tracking-ID Registry Tabelle speichert alle verfuegbaren Tracking-IDs mit Erstellungsdatum
  6. ~170 bestehende Tracking-IDs sind per Backfill-Script importierbar
**Plans:** 2/2 plans complete
Plans:
- [x] 02-01-PLAN.md — Product Library class + table creation + REST endpoints + plugin wiring
- [x] 02-02-PLAN.md — Telegram handler product storage integration + tracking-ID backfill script

### Phase 3: Token Extension
**Goal**: Posts saved with `amazon:last`, `amazon:heute`/`amazon:today`, or `amazon:gestern`/`amazon:yesterday` tokens are automatically converted to affiliate-card blocks
**Depends on**: Phase 2
**Requirements**: EDIT-01, EDIT-02, EDIT-05
**Success Criteria** (what must be TRUE):
  1. Saving a post with `amazon:last` converts it to an affiliate card using the most-recently-received product
  2. `amazon:heute` or `amazon:today` inserts ALL products received today as sequential affiliate cards
  3. `amazon:gestern` or `amazon:yesterday` inserts ALL products received yesterday as sequential affiliate cards
  4. Inline tokens (within text) link the first product and add cards below the paragraph
  5. No regressions in existing `amazon:ASIN` token processing
**Plans:** 2/2 plans complete
Plans:
- [x] 03-01-PLAN.md — Product Library date methods + Token Prepass class with unit tests
- [x] 03-02-PLAN.md — Wire prepass into handle_save_post + integration tests

### Phase 4: Editor Enhancements + Admin Page
**Goal**: Authors can visually pick a saved product from a dropdown in the block editor, and can inspect the full product library without database access
**Depends on**: Phase 2
**Requirements**: EDIT-03, EDIT-04, PLIB-04
**Success Criteria** (what must be TRUE):
  1. The affiliate card block's sidebar (InspectorControls) shows a dropdown listing stored products by title + ASIN, newest first
  2. Selecting a product from the dropdown hydrates the block identically to typing an ASIN manually — image, title, and affiliate link all render correctly
  3. Typing `amazon:last` or `amazon:last2` in the editor's ASIN field resolves the token live in the editor preview (before saving)
  4. The WordPress admin has a "Produkt-Bibliothek" page showing all stored products in a list table with bulk-delete capability
**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Settings + Telegram Webhook Pipeline | 2/3 | In Progress|  |
| 2. Product Library + Tracking-ID Registry | 2/2 | Complete   | 2026-03-25 |
| 3. Token Extension | 2/2 | Complete   | 2026-03-25 |
| 4. Editor Enhancements + Admin Page | 0/? | Not started | - |
