# Phase 1: Settings + Telegram Webhook Pipeline - Context

**Gathered:** 2026-03-25
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 1 delivers the complete Telegram-to-WordPress bot pipeline: Settings UI for credentials, webhook endpoint that receives Telegram messages, processes Amazon links (ShortURL resolution, ASIN extraction, tracking-ID management), replies via Telegram with affiliate URLs, and warns about unregistered tracking IDs. This phase does NOT include product storage (Phase 2), editor tokens (Phase 3), or dropdown pickers (Phase 4).

</domain>

<decisions>
## Implementation Decisions

### Bot Response Format
- **D-01:** Claude's Discretion — design a response format that includes the affiliate URL plus contextual info (ASIN, tracking-ID). Keep it useful but not cluttered.
- **D-02:** Error messages in German, consistent with Node-RED bot. Differentiate error cases: ungueltige ASIN, Shortlink nicht aufloesbar, ungueltiges Datum, unbekanntes Format (with help text listing valid inputs).
- **D-03:** Research additional useful error cases beyond what Node-RED handles (e.g., non-.de Amazon domain, timeout, rate limiting).

### Tracking-ID State
- **D-04:** Claude's Discretion — choose between wp_options (simple key-value) and leveraging Phase 2's product table for lastAsin. For Phase 1 (before product table exists), wp_options is the pragmatic choice for both trackingId and lastAsin.
- **D-05:** Tracking-ID state persists across plugin deactivation/reactivation — use wp_options with autoload=false.
- **D-06:** Default tracking-ID follows existing `derive_partner_tag()` logic: `meintechblog-YYMMDD-21` from current date.

### Tracking-ID Warning
- **D-07:** Claude's Discretion — design the warning trigger (when to check registry) and frequency (once per ID vs repeated). Recommended approach: warn on first use of an unregistered tracking-ID, then suppress until tracking-ID changes.
- **D-08:** Claude's Discretion — design the "done/ok/angelegt" confirmation flow. Recommended: user sends "done" → bot saves the current tracking-ID to the registry (with its date) and confirms.
- **D-09:** The tracking-ID registry table is created in Phase 2 (TRID-01). Phase 1 needs to CHECK the registry but may use a temporary wp_options list until Phase 2 delivers the proper table. Alternatively, Phase 1 creates the table early since TRID-03/04 depend on it.

### Settings UI
- **D-10:** Telegram settings live in a dedicated new tab "Telegram Bot" — third tab alongside "Einstellungen" and "Affiliate Audit".
- **D-11:** Claude's Discretion — webhook status display. Recommended: simple green/red badge plus optional "Status pruefen" button that calls Telegram getWebhookInfo.
- **D-12:** Bot-Token field uses `type="password"` (like client_secret). Chat-ID is a plain text field (optional). Webhook-Secret is auto-generated or manually settable.

### Webhook Security
- **D-13:** Webhook endpoint uses `X-Telegram-Bot-Api-Secret-Token` header validation (set during webhook registration). Permission callback returns true (Telegram cannot send WP auth headers).
- **D-14:** Chat-ID filtering is optional — if configured, only messages from that chat ID are processed. Others get silently ignored (return 200 to avoid Telegram retry storm).
- **D-15:** Always return HTTP 200 to Telegram, even on errors — process asynchronously where needed.

### Claude's Discretion
- Bot response format details (URL + context vs just URL)
- Exact error message wording and additional error cases
- Tracking-ID state storage approach
- Warning trigger logic and frequency
- Webhook status display implementation

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Node-RED Flow (Source of Truth for Bot Logic)
- `flows.json` — Complete Node-RED flow with all input formats, ASIN extraction regex, tracking-ID logic, error messages, and response format. This is the specification to port.

### Existing Plugin Code (Integration Points)
- `includes/class-mtb-affiliate-settings.php` — Settings pattern: defaults(), get_all(), save(), sanitize(), register(). New Telegram fields follow this exact pattern.
- `includes/class-mtb-affiliate-rest-controller.php` — REST route registration pattern: register_rest_route() with args, permission_callback, callback. Webhook endpoint follows same pattern with `permission_callback => '__return_true'`.
- `includes/class-mtb-affiliate-plugin.php` — Plugin bootstrap, boot() hook wiring, settings page rendering with tabs, activate() hook. New tab and services wire here.
- `includes/class-mtb-affiliate-amazon-client.php` — `derive_partner_tag()` and `extract_partner_tag()` methods — reuse for tracking-ID logic.

### Research Findings
- `.planning/research/PITFALLS.md` — Critical: Telegram timeout/retry behavior, wp_remote_get vs wp_safe_remote_get, dbDelta formatting
- `.planning/research/ARCHITECTURE.md` — New class structure: MTB_Affiliate_Telegram_Handler, MTB_Affiliate_Url_Resolver
- `.planning/research/STACK.md` — No external dependencies needed, all WordPress core

### Backfill Data
- `.planning/data/tracking-ids-backfill.txt` — ~170 existing tracking IDs for registry import (Phase 2, but registry table may be created early in Phase 1)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `MTB_Affiliate_Amazon_Client::derive_partner_tag($postDate)` — generates `meintechblog-YYMMDD-21` from date string. Reuse for tracking-ID derivation from Telegram date commands.
- `MTB_Affiliate_Amazon_Client::extract_partner_tag($url)` — extracts tag from Amazon URL. Reuse for ASIN extraction patterns.
- `MTB_Affiliate_Rest_Controller::normalize_asin()` — ASIN validation regex `/^[A-Z0-9]{10}$/`. Reuse in Telegram handler.
- `MTB_Affiliate_Settings` — complete settings CRUD pattern. Extend with telegram_bot_token, telegram_chat_id, telegram_webhook_secret fields.

### Established Patterns
- Singleton plugin class with composed services (constructed in `__construct`, wired in `boot()`)
- REST routes registered via `rest_api_init` hook
- Settings stored as single serialized array in wp_options
- Tabbed admin UI with inline PHP rendering (no separate template files for settings)
- Error responses use `WP_Error` with status codes

### Integration Points
- `MTB_Affiliate_Plugin::boot()` — wire new Telegram handler service
- `MTB_Affiliate_Plugin::__construct()` — instantiate new classes
- `MTB_Affiliate_Plugin::render_settings_page()` — add "Telegram Bot" tab
- `rest_api_init` hook — register webhook endpoint
- `register_activation_hook` — create tracking-ID registry table if needed

</code_context>

<specifics>
## Specific Ideas

- Port ALL Node-RED input formats exactly: ASIN, Amazon-URL, Shortlink (amzn.to/amzn.eu), YYMMDD, DD.MM.YY/YYYY (flexible 1-2 digit day/month), "heute", "reset"
- Port ALL Node-RED error cases: ungueltige ASIN, Shortlink-Fehler, ungueltiges Datum, Fallback-Hilfetext
- Research additional error cases that make sense (non-.de domains, timeouts, etc.)
- Tracking-ID backfill data has potential typos to handle: `eintechblog-231104-21` (missing 'm'), `meintechblog-2009011-21` (7 digits)

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-settings-telegram-webhook-pipeline*
*Context gathered: 2026-03-25*
