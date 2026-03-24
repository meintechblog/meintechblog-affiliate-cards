# Project Research Summary

**Project:** meintechblog-affiliate-cards — Telegram-to-WordPress Affiliate Pipeline
**Domain:** WordPress Plugin — Webhook Receiver + Custom DB + Gutenberg Editor Enhancements
**Researched:** 2026-03-25
**Confidence:** HIGH

## Executive Summary

This milestone ports a working Node-RED Amazon affiliate bot into an existing WordPress plugin (meintechblog-affiliate-cards v0.2.30). The plugin already ships a Gutenberg affiliate card block, Amazon Creators API integration, and a REST controller — the new work adds three things on top: a Telegram webhook receiver that ingests Amazon product links, a persistent product library (custom DB table), and editor enhancements that let the author insert the most-recently-received product with a token (`amazon:last`) or a dropdown picker. The entire implementation must use zero external dependencies (no Composer, no npm additions) and must fit the plugin's existing flat-class architecture in `includes/`.

The recommended approach is three new PHP classes (`MTB_Affiliate_Telegram_Handler`, `MTB_Affiliate_Product_Library`, `MTB_Affiliate_Url_Resolver`) plus targeted modifications to four existing classes (`MTB_Affiliate_Settings`, `MTB_Affiliate_Rest_Controller`, `MTB_Affiliate_Post_Processor`, `index.js`). All Telegram communication uses raw WordPress HTTP API + PHP core — no library wrapper. The product library is a custom `$wpdb` table created via `dbDelta()`. The build order is strictly dependency-driven: ProductLibrary first, then Settings, then UrlResolver, then TelegramHandler, then REST routes, then the editor JS enhancements.

The dominant risk is the Telegram webhook retry storm: if the handler does not return HTTP 200 within Telegram's timeout window (~5s), Telegram retries the same message up to 8 times causing duplicate product inserts. The second risk is security — the endpoint is publicly reachable and must verify `X-Telegram-Bot-Api-Secret-Token` with `hash_equals()` before touching any business logic. Both risks must be designed in during Phase 1; they cannot be patched after the fact without architectural changes.

---

## Key Findings

### Recommended Stack

The project adds no new libraries. Every new capability is built on WordPress core and PHP core primitives already used by the existing plugin. Telegram webhook handling uses `file_get_contents('php://input')` + `json_decode` (raw POST body) and `wp_remote_post` for outbound `sendMessage`. ShortURL resolution uses `wp_remote_get` with `redirection => 10` — specifically NOT `wp_safe_remote_get`, which blocks intermediate Amazon CDN redirects. The custom product table uses `dbDelta()` from `wp-admin/includes/upgrade.php`. Bot settings extend the existing `wp_options`-backed `MTB_Affiliate_Settings` class.

**Core technologies (new additions only):**
- Telegram Bot API 9.5 (webhook mode): Receive POST updates, send `sendMessage` — no library, raw HTTP
- WordPress REST API (`register_rest_route`): Expose `/telegram`, `/products`, `/products/last` endpoints — zero new infrastructure
- `$wpdb` + `dbDelta()`: Custom `{prefix}mtb_affiliate_products` table — schema must follow exact `dbDelta` formatting rules
- `wp_remote_get` (not safe variant): ShortURL resolution for `amzn.to` / `amzn.eu` with up to 10 redirects
- `hash_equals()` + `X-Telegram-Bot-Api-Secret-Token`: Constant-time webhook security check
- `wp_options` (`get_option` / `update_option`, `autoload=false`): Tracking-ID state persistence, replacing Node-RED flow context variables

### Expected Features

**Must have (table stakes — v1.0, workflow broken without these):**
- Bot Token + Chat-ID stored in Plugin Settings — prerequisite for everything
- Telegram webhook REST endpoint with secret-token verification and chat_id allowlist
- Short URL resolution for `amzn.to` / `amzn.eu` shares from mobile
- ASIN extraction from resolved Amazon URLs + affiliate link generation
- Bot reply via `sendMessage` confirming processing with affiliate URL
- Tracking-ID management (`heute`, `YYMMDD`, `DD.MM.YY`, `reset`) via `wp_options`
- Product Library DB table persisting all received products
- `amazon:last` editor token resolving to most-recently-received ASIN
- Dropdown picker in block InspectorControls for visual ASIN selection

**Should have (differentiators — v1.x after validation):**
- `amazon:lastN` tokens (e.g. `amazon:last2`) for comparison-post workflows — same code path, parameterized
- Product Library admin page (`WP_List_Table`) for inspection and bulk-delete without DB access

**Defer (v2+):**
- Webhook URL display with one-click copy in Settings (ergonomic, not functional)
- Product Library search/filter (only relevant beyond ~200 products, ~1-2 years out at current posting rate)

**Anti-features to explicitly reject:**
- Telegram bot commands (`/start`, `/help`) — zero value for single-user workflow, adds dispatch complexity
- Webhook auto-registration on plugin activate — timing unreliable, SSL may not be trusted
- Multi-chat-ID support — conflates state, unnecessary for stated use case
- Polling via WP-Cron — not real-time, incompatible with <30-second workflow goal

### Architecture Approach

The plugin follows a flat-class, thin-controller pattern with no autoloader and no namespace hierarchy. New classes drop directly into `includes/` with `require_once`, are instantiated in `MTB_Affiliate_Plugin::__construct()`, and are wired via `rest_api_init`. The three new classes have no circular dependencies: `ProductLibrary` has none, `UrlResolver` has none, `TelegramHandler` depends on both. The editor JS (`index.js`) is modified in-place — the new dropdown and `amazon:lastN` token path are extensions of the existing `useEffect`/`subscribe` hydration pattern.

**Major components and responsibilities:**

1. `MTB_Affiliate_Product_Library` (NEW) — `dbDelta` schema creation, CRUD via `$wpdb`, `get_last(N)` query
2. `MTB_Affiliate_Url_Resolver` (NEW) — `wp_remote_get` ShortURL expansion, ASIN regex extraction, fallback logic
3. `MTB_Affiliate_Telegram_Handler` (NEW) — Parse Telegram payload, apply chat_id guard, dispatch to UrlResolver + ProductLibrary, call sendMessage
4. `MTB_Affiliate_Rest_Controller` (MODIFIED) — Register `/telegram` (POST), `/products` (GET), `/products/last` (GET) routes; delegate to handler/library
5. `MTB_Affiliate_Settings` (MODIFIED) — Add `telegram_bot_token`, `telegram_chat_id`, `telegram_webhook_secret` fields
6. `MTB_Affiliate_Post_Processor` (MODIFIED) — Pre-pass resolving `amazon:lastN` tokens to concrete ASINs before existing token scanner runs
7. `index.js` (MODIFIED) — Dropdown picker in `InspectorControls` + extend `extractAmazonToken()` for `amazon:last(\d*)` pattern

**Key data flows:**
- Telegram → `wp_remote_get` ShortURL resolution → ASIN extraction → `$wpdb->insert()` → `sendMessage` confirmation
- Editor mount → `apiFetch` GET `/products` → `SelectControl` dropdown → existing `hydrateAffiliateBlock()` call
- `amazon:last` token in paragraph → `save_post` pre-pass OR editor subscriber → GET `/products/last` → concrete ASIN → existing block insertion

### Critical Pitfalls

1. **Telegram retry storm from slow webhook response** — Return HTTP 200 before any `wp_remote_get` or remote calls. Set `timeout => 5` on ShortURL resolution; treat timeout as unresolvable rather than blocking. Verify with `getWebhookInfo` showing `pending_update_count: 0`.

2. **Open write endpoint without secret-token verification** — Verify `X-Telegram-Bot-Api-Secret-Token` with `hash_equals()` as the absolute first check in the handler. Use a separate random 32-char hex `webhook_secret` (not the bot token) configured in `setWebhook`. Apply chat_id allowlist as second line of defence.

3. **dbDelta silent failure from formatting violations** — Use exactly two spaces before `(id)` after `PRIMARY KEY`, `KEY` not `INDEX`, no `IF NOT EXISTS`, each column on its own line. Always `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` before calling. Verify table exists with `SHOW TABLES LIKE` after creation.

4. **Token pipeline collision between `amazon:last` and `amazon:ASIN`** — The `amazon:lastN` pre-pass in `MTB_Affiliate_Post_Processor` must run before the existing token scanner, and must only target paragraph-level tokens, not block attributes already in serialized Gutenberg comment JSON.

5. **`wp_safe_remote_get` blocking Amazon redirect chain** — Amazon CDN intermediate redirects fail `wp_http_validate_url`. Use `wp_remote_get` (not safe variant) for ShortURL resolution with `sslverify => true`. Handle `WP_Error` return by storing `asin = null` and not crashing the webhook handler.

---

## Implications for Roadmap

Based on dependency graph from ARCHITECTURE.md and pitfall-to-phase mapping from PITFALLS.md, four phases are the natural grouping:

### Phase 1: Telegram Webhook Pipeline

**Rationale:** The webhook receiver is the entry point for all new data. Everything else — product library writes, editor enhancements — depends on products arriving via Telegram. Security and timeout handling must be correct from day one; retrofitting is costly. The Node-RED `flows.json` provides an authoritative spec for business logic, making this the most well-understood phase.

**Delivers:** End-to-end bot pipeline: Telegram message arrives → ShortURL resolved → ASIN extracted → `sendMessage` confirmation with affiliate URL. Tracking-ID state management included. Webhook endpoint live at `/wp-json/mtb-affiliate-cards/v1/telegram`.

**Implements:**
- `MTB_Affiliate_Url_Resolver` (new)
- `MTB_Affiliate_Telegram_Handler` (new)
- `MTB_Affiliate_Settings` extension for bot_token, chat_id, webhook_secret
- REST route registration for `/telegram`

**Avoids:**
- Telegram retry storm (200 before any remote calls)
- Open endpoint (secret-token check first)
- `wp_safe_remote_get` blocking redirects

### Phase 2: Product Library (Custom DB Table)

**Rationale:** The product library is the persistence layer that the editor features (dropdown, `amazon:last` tokens) depend on. It has no upstream dependencies — it can be built in parallel with Phase 1 or immediately after. `dbDelta` pitfalls demand the schema be frozen before writing code; define all columns upfront.

**Delivers:** `{prefix}mtb_affiliate_products` table created on plugin activation. CRUD via `$wpdb`. `get_last(N)` query for editor consumption. REST endpoints `/products` and `/products/last`. Phase 1 can write to it once Phase 2 is merged.

**Implements:**
- `MTB_Affiliate_Product_Library` (new)
- REST routes for GET `/products` and GET `/products/last`
- Schema version option (`mtb_affiliate_db_version`) with `dbDelta` version gate
- `create_table()` wired into `MTB_Affiliate_Plugin::activate()`

**Avoids:**
- dbDelta silent failure (formatting, version gate, post-creation assertion)
- dbDelta schema drift (full column set defined upfront, documented)

### Phase 3: Token Extension (`amazon:last` / `amazon:lastN`)

**Rationale:** Server-side token resolution in `MTB_Affiliate_Post_Processor` extends the existing save_post pipeline. Depends on Phase 2 (ProductLibrary `get_last(N)` available). Must define execution order (pre-pass before existing scanner) before writing any code to avoid token collision pitfall.

**Delivers:** Posts saved with `amazon:last` paragraphs are automatically converted to affiliate-card blocks using the most recently received product. `amazon:last2` / `amazon:lastN` inserts N sequential blocks.

**Implements:**
- `MTB_Affiliate_Post_Processor` pre-pass with injected `$tokenResolver` callable
- Regex extension for `amazon:last(\d*)` pattern

**Avoids:**
- Token pipeline collision (strict execution order: lastN resolver before ASIN scanner)
- `amazon:last` persisting unsolved in saved post content

### Phase 4: Editor Enhancements (Dropdown + JS Tokens)

**Rationale:** Browser-side enhancements are last because they depend on the `/products` REST endpoints from Phase 2 and are independent from the server-side token processing in Phase 3. Editor JS changes have backward-compatibility risk (block attribute schema); test against existing saved blocks before shipping.

**Delivers:** Dropdown picker in `InspectorControls` showing recent products by title + ASIN. `amazon:last(\d*)` token support in the editor's live token subscriber. Refresh button for stale dropdown state.

**Implements:**
- `index.js` `SelectControl` in `InspectorControls` with `apiFetch` on mount
- `extractAmazonToken()` regex extension for `amazon:last(\d*)` branch
- Refresh button with `isRefreshing` state flag

**Avoids:**
- Block attribute backward-compat break (safe empty default for any new attribute)
- Stale dropdown (explicit refresh button + "Lade..." loading state)
- Bot Token leaking to JS (never pass via `wp_localize_script`)

### Phase 5 (Optional, v1.x): Admin Product Library Page

**Rationale:** Only needed once the library grows enough that the dropdown is insufficient for auditing. Low risk, isolated feature. Defer until Phase 1-4 are running and daily use confirms the need.

**Delivers:** `WP_List_Table`-based admin page showing all products with bulk-delete. No inline edit needed.

### Phase Ordering Rationale

- Phase 1 before Phase 2: Webhook pipeline gives immediate value; ProductLibrary writes can be a stub that logs until Phase 2 merges — or implement in parallel if two tracks available.
- Phase 2 before Phases 3 and 4: Both editor features require `get_last(N)` and the REST `/products` endpoints.
- Phase 3 before Phase 4: Server-side token pre-pass should be validated independently before adding JS-side token expansion of the same semantics.
- Phase 4 last: Most user-facing polish; depends on all backend work being stable.

### Research Flags

Phases needing no additional research (standard, well-documented patterns):
- **Phase 2:** `dbDelta` table creation is a canonical WordPress pattern; all edge cases documented in PITFALLS.md
- **Phase 3:** Token pre-pass pattern is a straightforward PHP extension; ARCHITECTURE.md documents the execution contract
- **Phase 5:** `WP_List_Table` is a well-established WordPress admin pattern

Phases where implementation should verify assumptions in real environment:
- **Phase 1:** Confirm `wp_remote_get` with `redirection => 10` successfully follows `amzn.to` chain on the live meintechblog.de hosting environment. Amazon redirect chains have been observed to vary by CDN region.
- **Phase 4:** Confirm `block.json` attribute addition with `"default": ""` produces no Gutenberg validation warning against existing saved blocks on the live site before shipping.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified against official WordPress and Telegram docs; no external dependencies reduces surface area of unknowns |
| Features | HIGH | Node-RED `flows.json` is the authoritative spec; feature set directly ported from proven production workflow |
| Architecture | HIGH | Based on direct code inspection of all existing plugin classes; build order derived from actual dependency graph |
| Pitfalls | HIGH | Pitfalls sourced from official docs (WordPress, Telegram Bot API) and verified against actual code patterns in the existing plugin |

**Overall confidence: HIGH**

### Gaps to Address

- **ShortURL resolution on live hosting:** `wp_remote_get` redirect-following behavior can vary if the WordPress install sits behind a proxy or if the host restricts outbound HTTP. Verify in Phase 1 with a real `amzn.to` URL before assuming the resolver works.
- **Telegram `getWebhookInfo` verification approach:** The PITFALLS checklist specifies checking `pending_update_count: 0` post-deploy. Document the manual verification step in the phase plan since it requires a live bot token and registered webhook.
- **`dbDelta` column set finalization:** PITFALLS explicitly warns against schema drift. The column set in STACK.md and ARCHITECTURE.md is slightly inconsistent (`detail_url` vs `url`, `image_url` lengths). Resolve the canonical schema before writing Phase 2 code — treat this as a pre-phase decision, not a mid-implementation one.

---

## Sources

### Primary (HIGH confidence)
- Telegram Bot API 9.5 official docs — `setWebhook`, `sendMessage`, `X-Telegram-Bot-Api-Secret-Token` header
- Telegram Webhook Guide (core.telegram.org/bots/webhooks) — HTTPS requirement, port constraints, retry behavior
- WordPress Developer Reference — `dbDelta()`, `register_rest_route`, `wp_remote_get`, `wp_safe_remote_get`, `hash_equals()`
- WordPress Block Editor docs — `SelectControl`, `@wordpress/api-fetch`
- Direct code inspection: all PHP classes in `includes/` (plugin v0.2.30)
- Direct code inspection: `blocks/affiliate-cards/index.js` (Gutenberg block)
- Direct code inspection: `flows.json` (Node-RED reference implementation — authoritative business logic spec)

### Secondary (MEDIUM confidence)
- Community dbDelta migration patterns (Voxfor) — DB version option pattern, confirmed against official docs
- WP_List_Table example (pmbaldha GitHub) — admin list table structure
- Gutenberg useSelect + REST API patterns (permanenttourist.ch) — editor data-fetch patterns

### Tertiary (LOW confidence)
- ASIN regex pattern (GreenFootballs gist) — `/dp/([A-Z0-9]{10})/` extraction; community source, manually verified against known Amazon URL structures but not exhaustive

---

*Research completed: 2026-03-25*
*Ready for roadmap: yes*
