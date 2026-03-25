---
phase: "03-token-extension"
plan: "01"
subsystem: "token-prepass"
tags: [php, token-resolution, product-library, unit-tests]
dependency_graph:
  requires: []
  provides: [date-query-methods, token-prepass-engine]
  affects: [includes/class-mtb-affiliate-product-library.php, includes/class-mtb-affiliate-token-prepass.php]
tech_stack:
  added: []
  patterns: [constructor-injection, test-double-subclass]
key_files:
  created:
    - includes/class-mtb-affiliate-token-prepass.php
    - tests/test-token-prepass.php
  modified:
    - includes/class-mtb-affiliate-product-library.php
decisions:
  - "Removed 'final' from MTB_Affiliate_Product_Library to allow test double subclassing per plan spec"
  - "Used 'match' expression in resolve_keyword() for clean keyword-to-library-call mapping"
  - "Fast-path check (strpos for any shorthand keyword) avoids regex overhead on posts without shorthand tokens"
metrics:
  duration: "107s"
  completed_date: "2026-03-25"
  tasks_completed: 2
  files_modified: 3
---

# Phase 03 Plan 01: Token Pre-Pass Engine Summary

**One-liner:** Date-query methods added to Product Library + MTB_Affiliate_Token_Prepass class resolves amazon:last/heute/today/gestern/yesterday shorthand tokens to concrete amazon:ASIN paragraphs before the ASIN pipeline.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Add date-based query methods to Product Library | `dd49a80` | includes/class-mtb-affiliate-product-library.php |
| 2 | Create MTB_Affiliate_Token_Prepass class with unit tests | `fe95bcf` | includes/class-mtb-affiliate-token-prepass.php, tests/test-token-prepass.php, includes/class-mtb-affiliate-product-library.php |

## What Was Built

### Product Library Date Queries (Task 1)

Three new public methods added to `MTB_Affiliate_Product_Library`:

- `get_products_by_date(string $date): array` — queries all products with `DATE(received_at) = %s` (UTC). Validates Y-m-d format before querying. Returns `ARRAY_A` rows sorted `received_at DESC`.
- `get_products_today(): array` — wrapper calling `get_products_by_date(gmdate('Y-m-d'))`.
- `get_products_yesterday(): array` — wrapper calling `get_products_by_date(gmdate('Y-m-d', strtotime('-1 day')))`.

All three use `$wpdb->prepare()` for SQL safety.

### Token Pre-Pass Engine (Task 2)

`MTB_Affiliate_Token_Prepass` class with a single public method `resolve(string $content): string`:

- **Fast path:** If no shorthand keyword exists in content, returns immediately unchanged.
- **Standalone tokens** (own paragraph): Replaced with N `<!-- wp:paragraph --><p>amazon:ASIN</p><!-- /wp:paragraph -->` blocks. Zero products = paragraph removed silently.
- **Inline tokens** (within text): First ASIN substituted inline; remaining ASINs appended as paragraphs after the current paragraph.
- **No-match tokens:** Paragraph removed (standalone) or token text removed (inline).
- **Regular `amazon:ASIN` tokens:** Pass through unchanged (no shorthand keyword present).
- **Case-insensitive:** `amazon:Last`, `amazon:HEUTE`, `amazon:YESTERDAY` all resolve correctly.

### Unit Tests (Task 2)

`tests/test-token-prepass.php` — 10 test cases, all pass:

1. Standalone `amazon:last` with one product → single ASIN paragraph
2. Standalone `amazon:heute` with 3 products → 3 ASIN paragraphs
3. Standalone `amazon:gestern` with 2 products → 2 ASIN paragraphs
4. Standalone `amazon:today` (English alias) → same as `amazon:heute`
5. Standalone `amazon:yesterday` (English alias) → same as `amazon:gestern`
6. Token with no matching products → paragraph removed
7. Inline `amazon:last` within text → ASIN substituted inline, surrounding text preserved
8. Regular `amazon:B0D7955R6N` → passes through unchanged
9. Content with no `amazon:` tokens → passes through unchanged
10. Case-insensitive: `amazon:Last`, `amazon:HEUTE` both resolve

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Removed `final` modifier from MTB_Affiliate_Product_Library**
- **Found during:** Task 2 (test execution)
- **Issue:** `final class MTB_Affiliate_Product_Library` prevented `Test_Product_Library extends MTB_Affiliate_Product_Library` — the exact pattern the plan spec required for the test double.
- **Fix:** Removed `final` keyword from the class declaration. The class has no subclasses in production code and WordPress's wpdb dependency still enforces encapsulation at runtime. The change is backward-compatible.
- **Files modified:** `includes/class-mtb-affiliate-product-library.php`
- **Commit:** `fe95bcf`

## Known Stubs

None — both implemented classes are fully wired. The pre-pass receives a real `MTB_Affiliate_Product_Library` instance and calls live DB methods. Plan 02 will wire the pre-pass into the plugin's `handle_save_post` flow.

## Self-Check: PASSED

- [x] `includes/class-mtb-affiliate-product-library.php` — exists, syntax OK
- [x] `includes/class-mtb-affiliate-token-prepass.php` — exists, syntax OK
- [x] `tests/test-token-prepass.php` — exists, all 10 tests pass
- [x] Commit `dd49a80` — Task 1
- [x] Commit `fe95bcf` — Task 2
