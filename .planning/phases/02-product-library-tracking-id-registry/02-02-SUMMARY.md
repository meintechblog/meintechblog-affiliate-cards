---
phase: 02-product-library-tracking-id-registry
plan: "02"
subsystem: telegram-pipeline-integration
tags: [telegram, product-library, tracking-registry, backfill]
dependency_graph:
  requires: [MTB_Affiliate_Product_Library, MTB_Affiliate_Tracking_Registry]
  provides: [Telegram-to-ProductLibrary pipeline, tracking-ID backfill script]
  affects: [includes/class-mtb-affiliate-telegram-handler.php, includes/class-mtb-affiliate-plugin.php, scripts/backfill-tracking-ids.php]
tech_stack:
  added: []
  patterns: [constructor injection, optional dependency with null default, WP-CLI eval-file script]
key_files:
  created:
    - scripts/backfill-tracking-ids.php
  modified:
    - includes/class-mtb-affiliate-telegram-handler.php
    - includes/class-mtb-affiliate-plugin.php
decisions:
  - "save_product() called after update_option() in both ASIN paths so product is saved even if bot reply fails"
  - "productLibrary optional (nullable) in handler constructor for backwards compatibility"
  - "Backfill imports anomalous IDs as-is (eintechblog-*, facebook-2017-21, etc) — real historical IDs"
metrics:
  duration: ~120s
  completed: "2026-03-25"
  tasks: 2
  files: 3
---

# Phase 02 Plan 02: Telegram Pipeline Integration and Tracking-ID Backfill Summary

Telegram handler wired to Product Library for ASIN persistence on every bot message; backfill script imports ~170 historical tracking IDs idempotently.

## What Was Built

### Task 1: Product Library dependency in Telegram Handler

Modified `includes/class-mtb-affiliate-telegram-handler.php`:

- Added `private ?MTB_Affiliate_Product_Library $productLibrary` property
- Added optional 4th constructor parameter `?MTB_Affiliate_Product_Library $productLibrary = null`
- Added `save_product(string $asin): void` private helper that calls `$this->productLibrary->insert()` with ASIN, empty title/image_url, and computed `detail_url`
- Called `$this->save_product($asin)` after `update_option(LAST_ASIN_OPTION)` in Step 9 (Amazon URL path) and Step 10 (direct ASIN path)

Modified `includes/class-mtb-affiliate-plugin.php`:

- Removed duplicate `private MTB_Affiliate_Product_Library $productLibrary` property declaration (Rule 1 fix)
- Added `$this->productLibrary` as 4th argument to `new MTB_Affiliate_Telegram_Handler()`

### Task 2: Tracking-ID backfill script

Created `scripts/backfill-tracking-ids.php`:

- WP-CLI compatible (`wp eval-file scripts/backfill-tracking-ids.php`) and direct-execution safe
- Bootstraps WordPress via `wp-load.php` if `ABSPATH` not defined
- Reads `.planning/data/tracking-ids-backfill.txt` line by line
- Ensures registry table exists via `needs_upgrade()` / `create_table()`
- Calls `MTB_Affiliate_Tracking_Registry::register()` per line — idempotent (returns false for duplicates)
- Imports 201 lines including known anomalies (typos, facebook-*, klimahofmann-* etc) as real historical IDs
- Prints: `Backfill complete: {imported} imported, {skipped} skipped, {total} total lines.`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed duplicate property declaration in plugin class**
- **Found during:** Task 1
- **Issue:** `private MTB_Affiliate_Product_Library $productLibrary` was declared twice (lines 14 and 20), causing a fatal PHP error in strict PHP 8+ environments
- **Fix:** Removed the duplicate second declaration, keeping the single correct one
- **Files modified:** `includes/class-mtb-affiliate-plugin.php`
- **Commit:** 56c3564

## Known Stubs

None — Telegram handler now persists every ASIN to the DB. Title and image_url are empty strings at insert time, to be enriched later via Amazon Creators API (existing behavior at render time).

## Self-Check: PASSED

- `scripts/backfill-tracking-ids.php` exists
- `56c3564` (Task 1) — confirmed in git log
- `4df4ea0` (Task 2) — confirmed in git log
