---
phase: 04-editor-enhancements-admin-page
plan: 02
subsystem: ui
tags: [wordpress, admin, wp-list-table, bulk-delete, php]

# Dependency graph
requires:
  - phase: 02-product-library-tracking-id-registry
    provides: MTB_Affiliate_Product_Library class with insert/get_recent
provides:
  - delete_by_ids(array $ids): int method on MTB_Affiliate_Product_Library
  - MTB_Affiliate_Product_Library_List_Table WP_List_Table subclass with bulk-delete
  - Top-level "Affiliate Cards" admin menu with "Produkt-Bibliothek" submenu
  - render_product_library_page() renders list table in a POST form
affects:
  - future admin pages within the Affiliate Cards menu

# Tech tracking
tech-stack:
  added: []
  patterns: [WP_List_Table subclass pattern with process_bulk_action before prepare_items]

key-files:
  created:
    - includes/class-mtb-affiliate-product-library.php (copied from main + delete_by_ids added)
    - includes/class-mtb-affiliate-product-library-list-table.php
    - tests/test-product-library-admin.php
  modified:
    - includes/class-mtb-affiliate-plugin.php

key-decisions:
  - "Top-level Affiliate Cards menu slug mtb-affiliate-cards-menu is distinct from existing settings slug mtb-affiliate-cards — no restructuring of existing settings"
  - "process_bulk_action() called before $this->items in prepare_items() so deletes execute before table re-loads"
  - "check_admin_referer('bulk-produkte') matches WP_List_Table nonce for plural='produkte'"
  - "get_recent(200) used for v1 — no pagination needed at library scale"
  - "productLibrary added to worktree plugin class (not present in this branch — copied library file from main)"

patterns-established:
  - "WP_List_Table subclass: constructor takes domain object, get_columns/get_bulk_actions/column_cb/column_default/process_bulk_action/prepare_items"
  - "Bulk action pattern: check_admin_referer + current_user_can before delete"

requirements-completed: [PLIB-04]

# Metrics
duration: 15min
completed: 2026-03-25
---

# Phase 04 Plan 02: Produkt-Bibliothek Admin Page Summary

**WP_List_Table admin page for inspecting and bulk-deleting stored affiliate products, accessible via top-level "Affiliate Cards" menu**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-25T00:00:00Z
- **Completed:** 2026-03-25
- **Tasks:** 3 of 3 auto tasks complete (checkpoint:human-verify pending)
- **Files modified:** 4

## Accomplishments

- Added `delete_by_ids(array $ids): int` to `MTB_Affiliate_Product_Library` with intval cast and proper prepare() for SQL injection safety
- Created `MTB_Affiliate_Product_Library_List_Table` extending WP_List_Table with columns (checkbox, ASIN, Titel, Empfangen) and bulk-delete action
- Registered top-level "Affiliate Cards" admin menu (slug: mtb-affiliate-cards-menu, icon: dashicons-products, position 58) with "Produkt-Bibliothek" submenu in plugin
- All 6 unit assertions pass via lightweight WpdbStub (no real DB needed)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add delete_by_ids() to MTB_Affiliate_Product_Library + unit tests** - `201c4a3` (feat)
2. **Task 2: Create WP_List_Table subclass file** - `9b685f8` (feat)
3. **Task 3: Register top-level admin menu and Produkt-Bibliothek page** - `1d024f3` (feat)

_Note: Task 1 is TDD — test written first (RED), then implementation (GREEN), combined into one commit per plan spec_

## Files Created/Modified

- `includes/class-mtb-affiliate-product-library.php` - Copied from main branch (prerequisite), added `delete_by_ids()` method
- `includes/class-mtb-affiliate-product-library-list-table.php` - New WP_List_Table subclass for admin list view
- `tests/test-product-library-admin.php` - Standalone PHP test using WpdbStub, 6 assertions
- `includes/class-mtb-affiliate-plugin.php` - Added require_once, productLibrary property, register_product_library_menu hook, two new public methods

## Decisions Made

- Existing `register_settings_page()` / `add_options_page('mtb-affiliate-cards')` left completely untouched — new menu uses distinct slug `mtb-affiliate-cards-menu`
- `process_bulk_action()` runs first in `prepare_items()` so deleted products don't appear in the re-loaded table
- WpdbStub minimal approach for standalone PHP tests — no PHPUnit dependency required

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Copied class-mtb-affiliate-product-library.php from main into worktree**
- **Found during:** Task 1 setup
- **Issue:** This worktree branch predates Phase 02 — the product library class does not exist in this branch. The plan assumes it is already present (from main).
- **Fix:** Used `git show main:includes/class-mtb-affiliate-product-library.php` to copy the file into the worktree. This is the correct prerequisite file from which `delete_by_ids()` was then added.
- **Files modified:** includes/class-mtb-affiliate-product-library.php (created)
- **Verification:** php tests/test-product-library-admin.php passes with correct stub behavior
- **Committed in:** 201c4a3 (Task 1 commit)

**2. [Rule 3 - Blocking] Added productLibrary property and injection to plugin class**
- **Found during:** Task 3
- **Issue:** Worktree's plugin class does not have MTB_Affiliate_Product_Library injected (predates Phase 02). Plan assumes it is already present.
- **Fix:** Added private property declaration and `$this->productLibrary = new MTB_Affiliate_Product_Library();` in __construct().
- **Files modified:** includes/class-mtb-affiliate-plugin.php
- **Verification:** Property available to render_product_library_page()
- **Committed in:** 1d024f3 (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 3 - blocking prerequisites missing due to worktree branch predating Phase 02)
**Impact on plan:** Both auto-fixes necessary to unblock execution. No scope creep. Existing plugin functionality untouched.

## Issues Encountered

- Worktree branch `worktree-agent-a095064b` is based on a pre-Phase-02 commit. Files added in Phases 02 and 03 (product library, token prepass, tracking registry, etc.) are absent. Only the minimum required files (product library class) were brought in to execute this plan — no attempt was made to merge all of main.

## User Setup Required

None — no external service configuration required. Human verification checkpoint follows this plan.

## Next Phase Readiness

- Produkt-Bibliothek page is ready for FTP deploy and human verification (see Task 4 checkpoint)
- After verification, bulk-delete and admin menu are confirmed working in live WordPress
- When this branch merges to main, the productLibrary injection here will be superseded by the more complete version on main (which includes telegramHandler and trackingRegistry)

---
*Phase: 04-editor-enhancements-admin-page*
*Completed: 2026-03-25*
