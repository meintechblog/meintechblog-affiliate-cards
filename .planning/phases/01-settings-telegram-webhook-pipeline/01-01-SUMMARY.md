---
phase: 01-settings-telegram-webhook-pipeline
plan: 01
subsystem: settings-foundation
tags: [settings, tracking-registry, url-resolver, telegram, php]
dependency_graph:
  requires: []
  provides: [MTB_Affiliate_Settings.telegram_fields, MTB_Affiliate_Tracking_Registry, MTB_Affiliate_Url_Resolver]
  affects: [Plan 02 (Telegram Handler), Plan 03 (Settings UI)]
tech_stack:
  added: []
  patterns: [wpdb-dbDelta, wp_remote_get-redirect-following, WordPress-options-auto-generate]
key_files:
  created:
    - includes/class-mtb-affiliate-tracking-registry.php
    - includes/class-mtb-affiliate-url-resolver.php
  modified:
    - includes/class-mtb-affiliate-settings.php
    - meintechblog-affiliate-cards.php
decisions:
  - "bin2hex(random_bytes(16)) for webhook_secret auto-generation on first sanitize call — produces 32-char hex, no user action needed"
  - "wp_remote_get (not wp_safe_remote_get) for amzn.to resolution — safe variant blocks Amazon redirect chain (Pitfall 5)"
  - "B0-prefix ASIN pattern ported exactly from flows.json extractAsin() — intentionally narrower than normalize_asin() in REST controller"
  - "dbDelta PRIMARY KEY  (id) with two spaces — critical for silent failure avoidance (Pitfall 3)"
metrics:
  duration: "89s"
  completed_date: "2026-03-25"
  tasks_completed: 3
  files_changed: 4
---

# Phase 01 Plan 01: Foundation Classes and Settings Extension Summary

Extended MTB_Affiliate_Settings with three Telegram fields (auto-generates webhook_secret), created MTB_Affiliate_Tracking_Registry with dbDelta-safe table schema, and created MTB_Affiliate_Url_Resolver porting the ShortURL detection/resolution/ASIN-extraction logic from flows.json.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Extend MTB_Affiliate_Settings with Telegram fields | cc56e30 | includes/class-mtb-affiliate-settings.php |
| 2 | Create MTB_Affiliate_Tracking_Registry class | bc0384d | includes/class-mtb-affiliate-tracking-registry.php |
| 3 | Create MTB_Affiliate_Url_Resolver and wire require_once chain | 2a7b4c6 | includes/class-mtb-affiliate-url-resolver.php, meintechblog-affiliate-cards.php |

## What Was Built

**MTB_Affiliate_Settings extensions:**
- `defaults()` now returns `telegram_bot_token`, `telegram_chat_id`, `telegram_webhook_secret` (all default empty string)
- `sanitize()` trims all three telegram fields; auto-generates `telegram_webhook_secret` via `bin2hex(random_bytes(16))` when empty
- All existing fields (cta_label, badge_mode, auto_shorten_titles, marketplace, client_id, client_secret) unchanged

**MTB_Affiliate_Tracking_Registry (`includes/class-mtb-affiliate-tracking-registry.php`):**
- `create_table()`: uses `dbDelta` with strict two-space `PRIMARY KEY  (id)` format, `UNIQUE KEY tracking_id (tracking_id)`, no IF NOT EXISTS; stores DB version option after creation
- `table_name()`: returns `$wpdb->prefix . 'mtb_affiliate_tracking_ids'`
- `exists(string $trackingId): bool`: safe `$wpdb->prepare()` query
- `register(string $trackingId): bool`: guards empty input and duplicates; uses `$wpdb->insert()` with `current_time('mysql', true)`
- `needs_upgrade(): bool`: compares stored version option against `self::DB_VERSION`

**MTB_Affiliate_Url_Resolver (`includes/class-mtb-affiliate-url-resolver.php`):**
- `is_short_url(string $text): bool`: detects amzn.to/amzn.eu via regex
- `extract_short_url(string $text): ?string`: extracts first match, returns null if none
- `resolve(string $url): ?string`: `wp_remote_get` with timeout=5, redirection=10, custom user-agent; extracts final URL from response object or Location header fallback; returns null on wp_error
- `extract_asin(string $url): ?string`: B0-prefix ASIN pattern `/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i`

**Entry-point wiring:**
- `meintechblog-affiliate-cards.php` now has `require_once` for both new class files after `class-mtb-affiliate-plugin.php`

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None. All methods are fully implemented. No hardcoded empty values or placeholder returns.

## Self-Check: PASSED

Files verified:
- `includes/class-mtb-affiliate-settings.php` - FOUND
- `includes/class-mtb-affiliate-tracking-registry.php` - FOUND
- `includes/class-mtb-affiliate-url-resolver.php` - FOUND
- `meintechblog-affiliate-cards.php` - FOUND (with 2 new require_once lines)

Commits verified:
- cc56e30 - FOUND (Task 1)
- bc0384d - FOUND (Task 2)
- 2a7b4c6 - FOUND (Task 3)

All four PHP files pass `php -l` lint check.
