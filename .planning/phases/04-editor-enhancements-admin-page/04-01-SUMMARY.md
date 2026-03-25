---
phase: 04-editor-enhancements-admin-page
plan: "01"
subsystem: block-editor
tags: [gutenberg, product-picker, rest-api, inspector-controls]
dependency_graph:
  requires: [product-library-rest-endpoint]
  provides: [product-picker-ui]
  affects: [blocks/affiliate-cards/index.js]
tech_stack:
  added: []
  patterns: [write-only-picker, productsLoaded-guard, element.useState]
key_files:
  created: []
  modified:
    - blocks/affiliate-cards/index.js
decisions:
  - PRODUCTS_ENDPOINT constant added alongside HYDRATION_ENDPOINT (was missing from file despite plan stating it was already defined)
  - write-only picker: value always '' so dropdown never mirrors state and avoids re-render loops
  - productsLoaded guard prevents double-fetch on React StrictMode double-invocation
metrics:
  duration: "~5 minutes"
  completed_date: "2026-03-25"
  tasks_completed: 2
  files_modified: 1
requirements_satisfied:
  - EDIT-03
  - EDIT-04
---

# Phase 04 Plan 01: Product Picker Dropdown — Summary

Product picker SelectControl added to the affiliate card block's InspectorControls sidebar using `element.useState` for product list state and a single fetch on mount, with a write-only design that calls `updateItem('asin', ...)` identically to manual ASIN entry.

## What Changed in index.js

### Constants added (top of IIFE)
- `PRODUCTS_ENDPOINT = 'mtb-affiliate-cards/v1/products'` — new constant, was missing from file (plan stated it was already defined, but it was not)

### State variables added inside AffiliateCardsEdit (after hydrationAsinRef)
- `const [ products, setProducts ] = element.useState( [] )` — holds product list from REST API
- `const [ productsLoaded, setProductsLoaded ] = element.useState( false )` — prevents double-fetch

### useEffect added (empty deps, mount-only)
- Fetches `GET /wp-json/mtb-affiliate-cards/v1/products?limit=50` once on mount
- Sends `X-WP-Nonce` header from `window.wpApiSettings.nonce` for authentication
- Falls back to `[]` on non-ok response or network error
- Sets `productsLoaded = true` in both success and error paths

### SelectControl added in InspectorControls PanelBody
- Positioned after Produkt-ASIN TextControl, before Badge-Modus SelectControl
- Label: "Aus Bibliothek wählen"
- Guard: `products.length > 0 &&` — hides when library is empty/not loaded
- `value: ''` always — write-only picker, never mirrors `item.asin`
- `onChange`: calls `updateItem('asin', value.trim().toUpperCase())` — identical path to manual ASIN TextControl

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical Functionality] Added PRODUCTS_ENDPOINT constant**
- **Found during:** Task 1
- **Issue:** Plan's `<interfaces>` section stated `PRODUCTS_ENDPOINT = 'mtb-affiliate-cards/v1/products'` was "already defined constant" in the file, but it was absent. Without it, the fetch useEffect would throw a ReferenceError.
- **Fix:** Added `const PRODUCTS_ENDPOINT = 'mtb-affiliate-cards/v1/products';` alongside `HYDRATION_ENDPOINT` at top of IIFE.
- **Files modified:** blocks/affiliate-cards/index.js
- **Commit:** b8833e3

## Verification Status

Tasks 1 and 2 complete with code changes committed. Awaiting human verification in WordPress admin (checkpoint:human-verify).

Automated checks passed:
- `productsLoaded` appears in useState declaration (line 333) and useEffect guard (line 336)
- `PRODUCTS_ENDPOINT + '?limit=50'` present at line 344
- useEffect ends with `}, [] )` (empty deps array) at line 354
- `Aus Bibliothek wählen` present at line 506
- `value: ''` present at line 507 (write-only design confirmed)
- `products.length > 0` guard at line 505

## Self-Check: PASSED

Files created/modified:
- FOUND: /Users/hulki/codex/meintechblog-affiliate-cards/.claude/worktrees/agent-a864e5b4/blocks/affiliate-cards/index.js

Commits:
- b8833e3 — feat(04-01): add product-fetch state and useEffect to AffiliateCardsEdit
- 2eed60a — feat(04-01): render product picker SelectControl in block sidebar
