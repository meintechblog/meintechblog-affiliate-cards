---
phase: 03-token-extension
plan: 02
subsystem: save-post pipeline
tags: [token-prepass, post-processor, integration, wiring, pipeline]
dependency_graph:
  requires: [03-01]
  provides: [save_post shorthand token resolution, full pipeline integration tests]
  affects: [class-mtb-affiliate-plugin.php, handle_save_post]
tech_stack:
  added: []
  patterns: [prepass-then-processor, early-return guard, product-library injection]
key_files:
  created:
    - tests/test-token-prepass-integration.php
  modified:
    - includes/class-mtb-affiliate-plugin.php
    - meintechblog-affiliate-cards.php
    - includes/class-mtb-affiliate-product-library.php
    - includes/class-mtb-affiliate-token-prepass.php
    - tests/test-token-prepass.php
decisions:
  - "itemResolver provided in integration test run_pipeline() so inline tokens can produce affiliate-card blocks -- without it resolve_inline_items returns empty"
  - "Brought in product-library, token-prepass, test-token-prepass from 03-01 branch -- absent in this parallel worktree"
metrics:
  duration: "~8min"
  completed: "2026-03-25T01:56:52Z"
  tasks_completed: 2
  files_changed: 5
---

# Phase 03 Plan 02: Token Prepass Pipeline Wiring Summary

**One-liner:** Token Prepass wired before Post Processor in handle_save_post — amazon:last/heute/gestern resolve to ASIN blocks on save, proven by 7-case integration test.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Wire Token Prepass into handle_save_post | b3e7b6a | includes/class-mtb-affiliate-plugin.php, meintechblog-affiliate-cards.php, +3 prerequisite files |
| 2 | Integration test -- full pipeline with pre-pass + post processor | ac89eda | tests/test-token-prepass-integration.php |

## What Was Built

**Task 1 — Wiring:**
- Added `$productLibrary` property + instantiation to `MTB_Affiliate_Plugin` constructor
- Added `require_once class-mtb-affiliate-token-prepass.php` in plugin.php includes
- Added `require_once class-mtb-affiliate-product-library.php` in main plugin file (before plugin.php in boot chain)
- Inserted token prepass call in `handle_save_post()` between the `amazon:` fast-path check and the Post Processor creation
- Added post-prepass early-return: if prepass consumed all tokens but no ASINs produced (e.g. empty library), update post to remove dead tokens and return early

**Task 2 — Integration Tests:**
- `tests/test-token-prepass-integration.php`: 7 test cases proving the full pipeline
- `Test_Product_Library_Integration` stub overrides DB methods with in-memory fixtures
- `run_pipeline()` helper: creates library stub, runs prepass, runs processor (with itemResolver for inline blocks)
- All 7 tests pass

## Verification

- `php -l includes/class-mtb-affiliate-plugin.php` -- no syntax errors
- `php tests/test-token-prepass-integration.php` -- exits 0, prints "All integration tests passed."
- `MTB_Affiliate_Token_Prepass` referenced in plugin.php
- `prepass->resolve` (line 320) called before `processor->process` (line 347)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Prerequisite files missing in parallel worktree**
- **Found during:** Task 1
- **Issue:** class-mtb-affiliate-product-library.php, class-mtb-affiliate-token-prepass.php, tests/test-token-prepass.php absent in this worktree branch (created by 03-01 in another worktree)
- **Fix:** Restored all three files from the `fe95bcf` commit on worktree-agent-ac13a316 branch
- **Files modified:** includes/class-mtb-affiliate-product-library.php, includes/class-mtb-affiliate-token-prepass.php, tests/test-token-prepass.php
- **Commit:** b3e7b6a

**2. [Rule 1 - Bug] Test ASIN lengths were 11 chars (post-processor requires exactly 10)**
- **Found during:** Task 2, first test run
- **Issue:** Test fixtures used ASINs like B0TESTLAST1 (11 chars), B0TODAY0001 (11 chars). TOKEN_PATTERN requires exactly 10 uppercase alphanumeric chars
- **Fix:** Shortened all test ASINs to 10 chars: B0TESTLAST, B0TODAY001/002/003, B0YEST0001/0002
- **Files modified:** tests/test-token-prepass-integration.php

**3. [Rule 1 - Bug] Inline test needed itemResolver to produce affiliate-card blocks**
- **Found during:** Task 2, Test 4
- **Issue:** Plan said "no itemResolver for simplicity" but inline tokens require itemResolver in Post Processor to produce card blocks (resolve_inline_items returns empty without callable)
- **Fix:** Added minimal itemResolver to run_pipeline() that returns synthetic item data for any ASIN. Inline test now correctly asserts card block produced.
- **Files modified:** tests/test-token-prepass-integration.php

## Known Stubs

None — all pipeline paths produce real output. The itemResolver in tests is intentionally synthetic (test double, not production stub).

## Self-Check: PASSED

- includes/class-mtb-affiliate-plugin.php exists and has prepass wiring
- includes/class-mtb-affiliate-token-prepass.php exists
- tests/test-token-prepass-integration.php exists
- Commits b3e7b6a and ac89eda exist in git log
