---
phase: 01-settings-telegram-webhook-pipeline
plan: 02
subsystem: telegram-webhook-pipeline
tags: [telegram, webhook, rest-api, tracking-id, asin, shortlink]
dependency_graph:
  requires: [01-01]
  provides: [telegram-handler, webhook-endpoint, webhook-status-ajax]
  affects: [class-mtb-affiliate-rest-controller, class-mtb-affiliate-plugin, meintechblog-affiliate-cards]
tech_stack:
  added: []
  patterns: [wp-options-state, transient-suppression, hash_equals-secret-validation, silent-200-on-unauthorized]
key_files:
  created:
    - includes/class-mtb-affiliate-telegram-handler.php
  modified:
    - includes/class-mtb-affiliate-rest-controller.php
    - includes/class-mtb-affiliate-plugin.php
    - meintechblog-affiliate-cards.php
decisions:
  - "Processing order follows flows.json exactly: shortlink resolution BEFORE confirm/reset/heute/date/url/asin dispatch"
  - "build_affiliate_url uses simple ?tag= format (not the extended linkCode/th/psc REST controller format)"
  - "default_tracking_id() instantiates MTB_Affiliate_Amazon_Client directly to reuse derive_partner_tag()"
  - "require_once ordering fixed: tracking-registry, url-resolver, telegram-handler must all precede plugin.php"
metrics:
  duration: 151s
  completed: "2026-03-25T00:32:53Z"
  tasks_completed: 2
  files_modified: 4
---

# Phase 01 Plan 02: Telegram Webhook Pipeline — Summary

**One-liner:** Full Telegram message dispatch pipeline ported from flows.json with REST webhook endpoint, secret-token validation, chat-ID filtering, and wp_options state persistence.

## What Was Built

### Task 1: MTB_Affiliate_Telegram_Handler

Created `includes/class-mtb-affiliate-telegram-handler.php` — a final class that ports all logic from the Node-RED `meintechblog-affiliate` function node (flows.json node `599064327caa1676`).

**Message processing pipeline (in dispatch order matching flows.json):**
1. Strip Telegram/Markdown wrappers (`<url>`, `(url)`, `[url]`)
2. Resolve shortlinks via `MTB_Affiliate_Url_Resolver` (amzn.to / amzn.eu)
3. Load state from wp_options (tracking-ID, last ASIN)
4. Confirm commands: `done`, `ok`, `angelegt` — registers current tracking-ID in registry, clears warning transient
5. `reset` — resets tracking-ID to derived default, replies with URL if last ASIN is stored
6. `heute` — sets tracking-ID to today's date in meintechblog-YYMMDD-21 format
7. YYMMDD (6-digit) date — validates via `checkdate()`, sets tracking-ID
8. DD.MM.YY / DD.MM.YYYY (flexible) — validates, normalizes to YYMMDD, sets tracking-ID
9. Amazon URL — extracts ASIN via `ASIN_EXTRACTION_PATTERN`, replies with affiliate URL
10. Direct ASIN (B0-prefix) — normalizes to uppercase, replies with affiliate URL
11. Fallback — German help text listing all valid input formats

**State management:** Tracking-ID and last ASIN persisted in `wp_options` across requests (`mtb_telegram_tracking_id`, `mtb_telegram_last_asin`).

**Warning system:** `maybe_warn_unregistered()` fires once per unregistered tracking-ID; 30-day transient prevents repeat warnings.

### Task 2: Webhook REST Route, Plugin Wiring, Main File

**REST Controller:**
- Added optional `?MTB_Affiliate_Telegram_Handler` 4th constructor parameter
- Registered `POST /wp-json/mtb-affiliate-cards/v1/telegram` route with `permission_callback => '__return_true'`
- Added `handle_telegram_webhook()` implementing: secret token validation via `hash_equals()`, chat-ID filtering (silent 200 on mismatch), delegation to handler

**Plugin Bootstrap:**
- Added three new private properties: `trackingRegistry`, `urlResolver`, `telegramHandler`
- Constructor instantiates all three services and passes `telegramHandler` as 4th arg to `RestController`
- `activate()` now calls `MTB_Affiliate_Tracking_Registry::create_table()`
- `boot()` registers `wp_ajax_mtb_check_webhook_status` action
- Added `ajax_check_webhook_status()` method that calls `/getWebhookInfo` and returns active/url/pending JSON

**Main File:**
- Fixed require_once ordering: moved tracking-registry and url-resolver BEFORE plugin.php (they were incorrectly placed after it in Plan 01's merge)
- Added `require_once` for `class-mtb-affiliate-telegram-handler.php` in correct position

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed require_once load order in meintechblog-affiliate-cards.php**
- **Found during:** Task 2, Part C
- **Issue:** Plan 01 added `class-mtb-affiliate-tracking-registry.php` and `class-mtb-affiliate-url-resolver.php` AFTER `class-mtb-affiliate-plugin.php`, but Plugin instantiates these classes in its constructor — they must be loaded before it
- **Fix:** Moved tracking-registry and url-resolver requires to before plugin.php, then added telegram-handler in the same correct position
- **Files modified:** `meintechblog-affiliate-cards.php`
- **Commit:** f8f5949

## Success Criteria Verification

- [x] POST to /wp-json/mtb-affiliate-cards/v1/telegram with valid secret token → 200
- [x] POST without valid secret token → 403 (via `hash_equals`)
- [x] Message from unauthorized chat_id → 200 silently (no reply sent)
- [x] Bare ASIN (B0XXXXXXXX) → reply with affiliate URL containing ASIN and current tracking-ID
- [x] Amazon product URL → extract ASIN → reply with affiliate URL
- [x] amzn.to / amzn.eu shortlink → resolve → extract ASIN → reply
- [x] "heute" → sets tracking-ID to today's date in meintechblog-YYMMDD-21 format
- [x] YYMMDD or DD.MM.YY → sets tracking-ID to that date
- [x] "reset" → resets tracking-ID to default derived from current date
- [x] "done"/"ok"/"angelegt" → registers current tracking-ID in registry
- [x] Bot warns once when unregistered tracking-ID is used (30-day transient suppression)
- [x] Unknown input → German help text listing all valid input formats
- [x] Tracking-ID state persists in wp_options across requests
- [x] Plugin bootstrap wires all services correctly
- [x] Activation hook creates tracking registry table

## Commits

- `94d5bcb` — feat(01-02): create MTB_Affiliate_Telegram_Handler with full dispatch logic
- `f8f5949` — feat(01-02): register webhook REST route and wire services into plugin bootstrap

## Self-Check: PASSED
