---
phase: 02-product-library-tracking-id-registry
plan: "01"
subsystem: product-library
tags: [database, rest-api, product-library, crud]
dependency_graph:
  requires: []
  provides: [MTB_Affiliate_Product_Library, REST /products, REST /products/last]
  affects: [class-mtb-affiliate-plugin.php, class-mtb-affiliate-rest-controller.php, meintechblog-affiliate-cards.php]
tech_stack:
  added: []
  patterns: [dbDelta table creation, wpdb CRUD, WP_REST_Response]
key_files:
  created:
    - includes/class-mtb-affiliate-product-library.php
  modified:
    - includes/class-mtb-affiliate-plugin.php
    - includes/class-mtb-affiliate-rest-controller.php
    - meintechblog-affiliate-cards.php
decisions:
  - "productLibrary injected as 4th param (not 5th) into REST controller — this worktree does not yet include Phase 01 telegramHandler; will harmonize on merge"
metrics:
  duration: 112s
  completed: "2026-03-25"
  tasks: 2
  files: 4
---

# Phase 02 Plan 01: Product Library — Data Layer and REST API Summary

Product Library DB table with CRUD and REST endpoints for stored Amazon products via dbDelta pattern.

## What Was Built

### Task 1: MTB_Affiliate_Product_Library class

Created `includes/class-mtb-affiliate-product-library.php` following the exact dbDelta pattern from `MTB_Affiliate_Tracking_Registry`.

- `create_table()` creates `{prefix}mtb_affiliate_products` table with columns: `id`, `asin`, `title`, `detail_url`, `image_url`, `received_at`
- `insert(array $product)` accepts product array, auto-sets `received_at` to UTC, returns insert ID or false
- `get_recent(int $limit = 20)` returns products sorted by `received_at DESC`
- `get_last(int $n = 1)` returns Nth most recent product using `LIMIT 1 OFFSET n-1`
- `needs_upgrade()` follows same version-option pattern as Tracking Registry

Schema uses correct dbDelta formatting: two spaces before `PRIMARY KEY`, `KEY` not `INDEX`, each column on its own line.

### Task 2: Wiring into plugin bootstrap and REST API

Three files updated:

**meintechblog-affiliate-cards.php:** `require_once` for product-library added before `plugin.php`.

**class-mtb-affiliate-plugin.php:**
- Added `private MTB_Affiliate_Product_Library $productLibrary` property
- Instantiated in constructor before restController
- `activate()` now calls `MTB_Affiliate_Product_Library::create_table()`
- productLibrary passed to RestController constructor

**class-mtb-affiliate-rest-controller.php:**
- Added `private ?MTB_Affiliate_Product_Library $productLibrary` property
- Constructor updated with optional 4th parameter
- `GET /products` route added: returns `get_recent()` with capped limit (1-100)
- `GET /products/last(?P<n>\d*)` route added: returns single product or 404
- `get_products()` and `get_product_last()` callbacks implemented

## Deviations from Plan

### Parameter position adjustment

**Found during:** Task 2

**Issue:** Plan specified `productLibrary` as 5th constructor parameter (after `telegramHandler`), but this worktree branch does not include Phase 01's `telegramHandler` changes. Adding it as 5th would require a null placeholder for a parameter that does not exist in this branch.

**Fix:** Added `productLibrary` as 4th optional parameter. This is merge-compatible — when Phase 01 changes are integrated, the constructor signature will need to be reconciled by inserting `telegramHandler` as 4th and shifting `productLibrary` to 5th, or using named parameters if the merge order allows. The plugin constructor call uses positional `null` for badgeResolver and passes `$this->productLibrary` correctly for the current 4-param signature.

**Files modified:** `class-mtb-affiliate-rest-controller.php`, `class-mtb-affiliate-plugin.php`

**Commits:** 0a7bcdb

## Known Stubs

None — all endpoints return real DB data via productLibrary CRUD methods.

## Self-Check: PASSED

- `includes/class-mtb-affiliate-product-library.php` exists
- `e697f9e` (Task 1) — confirmed in git log
- `0a7bcdb` (Task 2) — confirmed in git log
