# Pitfalls Research

**Domain:** WordPress Plugin — Telegram Webhook + Custom DB + Gutenberg Editor Enhancements
**Researched:** 2026-03-25
**Confidence:** HIGH (verified against official WordPress docs, Telegram Bot API docs, and community sources)

---

## Critical Pitfalls

### Pitfall 1: Telegram Retries Your Webhook Because You Returned 200 Too Late

**What goes wrong:**
The Telegram Bot API requires HTTP 200 within a short window (typically 5–10 seconds). If the webhook handler does expensive work inline — ShortURL resolution via `wp_remote_get`, Amazon API calls, or any slow I/O — Telegram marks the delivery failed and retries up to 8 times with 60-second intervals. This means the same Telegram message is processed 8+ times, inserting duplicate product records into the library.

**Why it happens:**
Developers route the Telegram payload through the same synchronous path as a normal REST request. Amazon `amzn.to` short URLs require 2–3 HTTP hops to resolve, and each hop can take 1–2 seconds in a shared hosting environment. On a busy WordPress install, total time can exceed Telegram's timeout.

**How to avoid:**
Return `200 OK` immediately at the start of the webhook handler, then defer heavy work. The simplest WordPress-native pattern:
1. Validate the request (secret token header, chat_id filter) in under 1ms.
2. Store the raw update payload in a transient or a lightweight queue row.
3. Return 200 before any remote calls.
4. Process the payload (ShortURL resolution, ASIN extraction, Amazon API) in a `wp_schedule_single_event` that fires immediately on the next page load, or inline after `fastcgi_finish_request()` if the server supports it.

For a single-user bot with low message volume, an acceptable simpler approach is: resolve ShortURL inline but set `timeout` to 3 seconds in `wp_remote_get`, fall back to a raw ASIN regex if resolution times out, and always return 200 immediately.

**Warning signs:**
- Duplicate products appearing in the library after sending one Telegram message.
- WordPress error log showing the webhook endpoint being hit multiple times in 60-second intervals.
- Telegram Bot API `getWebhookInfo` returning `pending_update_count > 0` and `last_error_message` with timeout details.

**Phase to address:** Phase 1 (Telegram Webhook + ShortURL). Must be designed in from the start — retrofitting deferred processing is expensive.

---

### Pitfall 2: No Secret Token Verification = Open Write Endpoint

**What goes wrong:**
The WordPress REST API endpoint that receives Telegram updates is publicly reachable via HTTPS. Without verification, any actor who discovers the URL can POST arbitrary payloads and inject products into the library, or flood the webhook queue.

**Why it happens:**
Developers rely on "URL obscurity" (a random-looking REST route) and the bot token in the settings as the only guard. Neither is a verification mechanism — the bot token is never part of the incoming request in webhook mode.

**How to avoid:**
Use Telegram's `secret_token` parameter when calling `setWebhook`. Telegram will then include an `X-Telegram-Bot-Api-Secret-Token` header on every update. In the PHP handler:

```php
$incoming_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
$expected_secret = get_option('mtb_telegram_webhook_secret', '');
if ($expected_secret === '' || ! hash_equals($expected_secret, $incoming_secret)) {
    wp_send_json([], 401);
    exit;
}
```

Store a random 32-character hex string in options (generated on first save of Bot Token settings), and pass it as `secret_token` during manual webhook registration. Never put the Bot Token itself in the URL.

Additionally, verify `chat_id` against the configured Chat ID setting before storing any payload. This is the second line of defence.

**Warning signs:**
- Webhook route accessible without authentication (`permission_callback` returns `__return_true` or is omitted).
- Bot Token visible anywhere in client-side JS or REST response.
- No rate limiting on the endpoint.

**Phase to address:** Phase 1 (Telegram Webhook). Security check must be the first lines of the handler.

---

### Pitfall 3: dbDelta Silently Fails or Creates Wrong Schema

**What goes wrong:**
`dbDelta()` has strict, underdocumented formatting requirements. A single formatting mistake (wrong spacing, `INDEX` instead of `KEY`, `IF NOT EXISTS` in the statement, missing `require_once` for `upgrade.php`) causes the function to silently no-op or create an incomplete table. The plugin activates without error, but the table is malformed and `$wpdb->insert()` fails at runtime.

**Why it happens:**
dbDelta parses SQL with regex, not a proper SQL parser. It requires: two spaces between `PRIMARY KEY` and the definition, `KEY` not `INDEX`, no `IF NOT EXISTS`, each column on its own line, and backtick-quoted identifiers. Missing any one rule produces silent failure.

**How to avoid:**
- Always `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` before calling `dbDelta()`.
- Use `IF NOT EXISTS` only in a preliminary existence check — never inside the CREATE TABLE statement passed to dbDelta.
- Test the schema creation on a fresh WordPress install after each schema change.
- Store a `mtb_affiliate_db_version` option, compare it on every plugin boot, and re-run dbDelta only when version differs.
- After calling dbDelta, verify the table exists: `$wpdb->get_var("SHOW TABLES LIKE '{$table_name}'")`; log a notice if it's missing.

Example safe pattern:
```php
$sql = "CREATE TABLE {$table_name} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  asin varchar(10) NOT NULL,
  received_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY asin (asin)
) {$charset_collate};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
```
Note the two spaces before `(id)` after `PRIMARY KEY`.

**Warning signs:**
- Table missing after activation with no PHP error.
- `$wpdb->last_error` containing column or key errors on first insert.
- `dbDelta()` returning an empty array (no changes applied) when a new install is expected.

**Phase to address:** Phase 2 (Product Library / Custom DB Table). Verify schema immediately after activation hook in tests.

---

### Pitfall 4: dbDelta Cannot Remove Columns — Schema Drift on Upgrades

**What goes wrong:**
When the product library schema needs a new column (e.g., adding `raw_url`, `resolved_url`, `title` in a later version), dbDelta adds it. But if a column is renamed or removed, dbDelta leaves the old column untouched. Over time, the table has orphaned columns that waste space and confuse query code.

**Why it happens:**
dbDelta is additive-only by design. It has no awareness of "this column should no longer exist."

**How to avoid:**
- Never rename columns in dbDelta. Add a new column and migrate data explicitly.
- For the initial schema of the product library table, define all columns that will plausibly be needed for v1.0 upfront, reducing future migrations.
- If column removal becomes necessary, use an explicit `$wpdb->query("ALTER TABLE ... DROP COLUMN ...")` in a version-gated migration routine (not dbDelta).
- Version the DB schema in a constant (`MTB_AFFILIATE_DB_VERSION`) separate from the plugin version.

**Warning signs:**
- Column exists in DB but not in INSERT statement — silent orphan data.
- Code references a new column name that was "renamed" via dbDelta — PHP notices or empty results.

**Phase to address:** Phase 2. Define the full product library schema upfront. Document every column and its purpose before writing the CREATE statement.

---

### Pitfall 5: ShortURL Resolution Blocked by wp_safe_remote_get

**What goes wrong:**
`wp_safe_remote_get()` validates every URL in the redirect chain against `wp_http_validate_url()`. Amazon's `amzn.to` and `amzn.eu` resolve through several intermediate redirects, some of which may point to IP addresses or non-standard hostnames that the safe variant blocks. The function returns a `WP_Error` instead of following the chain, and ASIN extraction fails silently.

**Why it happens:**
Developers default to `wp_safe_remote_get()` for good security reasons, not realising Amazon's redirect chain includes CDN or regional hostnames that fail the safe URL validator.

**How to avoid:**
Use `wp_remote_get()` (not the safe variant) specifically for the ShortURL resolution step, since the target domain (`amzn.to`, `amzn.eu`) is known and trusted. Set explicit args:
```php
$response = wp_remote_get($short_url, [
    'timeout'     => 5,
    'redirection' => 10,
    'user-agent'  => 'Mozilla/5.0 (compatible; MTB-Affiliate-Bot/1.0)',
    'sslverify'   => true,
]);
```
Extract ASIN from the final `Location` header or the effective URL. Fall back to regex on `$short_url` itself if resolution fails — `amzn.to/dp/ASIN` sometimes appears in the original short URL.

Also handle the case where `wp_remote_get()` returns a `WP_Error` (network blocked, timeout) — treat as unresolvable, store `null` for ASIN, and let the user correct it manually rather than crashing.

**Warning signs:**
- `wp_remote_get` returns `WP_Error` with code `http_request_failed` on `amzn.to` URLs.
- Final resolved URL contains `/dp/` but ASIN regex matches nothing because the URL stopped at an intermediate redirect.
- Empty ASIN stored in product library for valid Amazon short links.

**Phase to address:** Phase 1 (ShortURL resolution). Write a dedicated `resolve_asin_from_url()` method with explicit fallback logic and test it against `amzn.to`, `amzn.eu`, and direct `amazon.de/dp/ASIN` inputs.

---

### Pitfall 6: Token Collision Between `amazon:ASIN` and `amazon:last` in the Token Scanner

**What goes wrong:**
The existing `MTB_Affiliate_Token_Scanner` uses the pattern `/^(?:amazon:)?([A-Z0-9]{10})$/` — it matches exactly 10 uppercase alphanumeric characters. The new `amazon:last`, `amazon:last2`, `amazon:lastN` tokens do not match this pattern, so if the scanner runs first it passes them through unchanged. But if the `amazon:last` resolver runs first and substitutes a real ASIN, the scanner then processes it again, potentially double-consuming the token.

The reverse problem: if `amazon:last` is introduced as a paragraph token that the scanner sees before the new resolver runs, the scanner ignores it (correct), but the new resolver must also handle the case where `amazon:ASIN` and `amazon:last` coexist in the same post — the `last` pointer must refer to the most recently received product, not the ASIN already in an existing block.

**Why it happens:**
The token resolution pipeline was designed for `amazon:ASIN` only. Adding a second token type with different semantics requires explicit ordering in the pipeline — the `amazon:last` resolver must run before the token scanner, or the scanner must be extended to understand both token types and resolve them in a single pass.

**How to avoid:**
Define a clear execution order in `handle_save_post` and in the editor's live token replacement:
1. `amazon:last` / `amazon:lastN` resolver runs first — substitutes `amazon:ASIN` for the resolved product.
2. The existing token scanner runs second — processes `amazon:ASIN` tokens as before.

In the editor (JavaScript), apply the same order in the `onChange` handler. Do not allow `amazon:last` to remain in saved post content — it must always be resolved before `wp_update_post`.

Also ensure the `amazon:last` resolver ignores token occurrences that already appear inside existing block attributes (i.e., inside `<!-- wp:meintechblog/affiliate-cards ... -->` JSON) — only paragraph-level tokens should trigger resolution.

**Warning signs:**
- `amazon:last` appearing in saved post content (visible in Code Editor).
- A newly received Telegram product being silently ignored because `amazon:last` resolved to an already-used ASIN.
- Existing `amazon:ASIN` blocks being re-processed after `amazon:last` resolution.

**Phase to address:** Phase 3 (Token Extension). Design the token resolution pipeline contract before writing any code. Document the execution order explicitly.

---

### Pitfall 7: Gutenberg Dropdown Loaded Once, Stale on New Product Arrival

**What goes wrong:**
The product library dropdown in the block editor is populated via `apiFetch` when the block mounts. If the user adds a product via Telegram while the editor is already open, the dropdown does not reflect the new product — the user must save and reload the page to see it.

This is not a bug per se, but users will perceive it as missing functionality and lose trust in the workflow.

**Why it happens:**
`apiFetch` in a `useEffect` with an empty dependency array runs once on mount. There is no push mechanism from server to editor, and no automatic polling.

**How to avoid:**
Two acceptable approaches for a single-user workflow:
1. Add a "Aktualisieren" (refresh) button next to the dropdown that re-fetches the product list on demand.
2. Auto-refresh the product list every 30 seconds using `useEffect` with a `setInterval` cleanup.

Option 1 is simpler and sufficient for the stated use case. Implement it with a `isRefreshing` state flag that shows a spinner on the button.

Explicitly document in the UI that "Products added via Telegram appear after clicking refresh or reloading the page."

**Warning signs:**
- User clicks dropdown immediately after sending Telegram message and sees no new product.
- No loading/refresh affordance in the dropdown UI.

**Phase to address:** Phase 4 (Dropdown Picker / Editor Enhancements). Decide on refresh strategy before building the dropdown component.

---

### Pitfall 8: Gutenberg block.json Attributes Not Extended Cleanly

**What goes wrong:**
Adding new attributes for the product library integration (e.g., `selectedLibraryProductId`) to `block.json` while the plugin is active on a live site causes existing saved blocks to be treated as having that attribute at its `default` value. This is harmless if the default is safe (empty string, null, 0). But if existing blocks have `items` populated and the new code path branches on the new attribute being absent, it may cause the block to render differently or fail validation.

**Why it happens:**
Gutenberg stores block attributes in HTML comments in post content. New attributes with safe defaults are backward-compatible, but if the Edit component logic depends on the new attribute being present to render correctly, old blocks lacking it will hit an unexpected code path.

**How to avoid:**
- Always provide a safe, empty default for every new attribute (`"default": ""`).
- Write Edit component code defensively: treat the new attribute's absence (or empty string) as "no library selection made" — fall through to the existing manual ASIN flow.
- Test with a post that has an existing saved block (no new attribute in stored content) before shipping.

**Warning signs:**
- Block editor shows "Block validation failed" after plugin update.
- Existing blocks render with unexpected placeholder state after update.

**Phase to address:** Phase 4 (Editor Enhancements). Validate backward compatibility with a saved-block regression test during development.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Inline ShortURL resolution in webhook handler (synchronous) | No queue complexity | Duplicate inserts on slow networks; Telegram retry storm | Only if p95 resolution time < 3s and message volume < 5/day |
| Store Bot Token in `wp_options` without encryption | Simple settings form | Token visible in DB export, backup files, phpMyAdmin | Acceptable for single-server personal blog; not for shared hosting |
| Skip DB version option, always run dbDelta on activation | Less code | dbDelta runs on every plugin update even when schema unchanged; slow for large tables | Never — always version-gate dbDelta |
| Use `$wpdb->prefix . 'mtb_products'` hardcoded everywhere | Simple | Hard to rename table; no central schema definition | Acceptable for single-plugin, but define as a constant |
| Return product list from REST endpoint without pagination | Simple endpoint | Endpoint slow if library grows to 500+ products | Acceptable for MVP with < 200 products |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Telegram Webhook | Register webhook pointing to wp-admin or a non-REST URL | Register against a `rest_api_init` route (`/wp-json/mtb-affiliate-cards/v1/telegram`) |
| Telegram Webhook | Use Bot Token as the URL secret (visible in server logs) | Use a separate random `secret_token` stored in options; pass it as `secret_token` param in `setWebhook` call |
| Telegram Bot API | Assume `message.text` always contains a URL | Messages can be empty, forwarded, photo captions — guard every field access |
| `wp_remote_get` on `amzn.to` | Use `wp_safe_remote_get` | Use `wp_remote_get` with `redirection => 10`; safe variant blocks intermediate redirects |
| `wp_remote_get` ASIN extraction | Parse final URL HTML for ASIN | Extract ASIN from redirect chain's `Location` headers or final effective URL path `/dp/ASIN` via regex — no HTML parsing needed |
| `dbDelta` | Include `IF NOT EXISTS` in CREATE TABLE | Remove `IF NOT EXISTS`; dbDelta handles idempotency itself |
| `dbDelta` | Call it without `require_once upgrade.php` | Always `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` first |
| Gutenberg `apiFetch` | Fetch product list inside `save()` function | Fetch only in `edit()` function; `save()` must be pure and cannot use hooks or async calls |
| Block attributes | Add attribute to block.json without default | Always define `"default"` — missing defaults cause Gutenberg validation warnings |

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| No index on `received_at` or `asin` in product library table | Dropdown query slow as library grows | Add `KEY asin (asin)` and `KEY received_at (received_at)` in CREATE TABLE | Noticeable at ~1000 rows; perceptible at ~200 if no index |
| Fetching all products for dropdown without LIMIT | Dropdown takes 500ms+ with large library | Add `LIMIT 100 ORDER BY received_at DESC` in the REST endpoint query | 200+ products |
| Running `wp_remote_get` for ShortURL in `save_post` hook | Page save hangs for 5s on slow DNS | Only resolve ShortURL in the Telegram webhook handler, not on post save | Every post save if this logic leaks into save_post |
| Calling Amazon Creator API on every block render for `amazon:last` | Render slower when last product has no cached data | Resolve `amazon:last` to a concrete ASIN at save time, not at render time | Every frontend page load |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| No `X-Telegram-Bot-Api-Secret-Token` header check | Any actor can POST to the webhook endpoint and inject products | Verify header with `hash_equals()` against stored secret on every request |
| No `chat_id` allowlist check | Any Telegram user who knows your bot can send products | Compare `$update['message']['chat']['id']` against the configured Chat ID setting |
| Bot Token returned in any REST response or JS `wp_localize_script` | Token can be used to send messages as your bot | Keep Bot Token server-side only; never pass it to the editor JS |
| Storing raw Telegram message payload including personal data | GDPR / data minimisation issue | Store only ASIN, resolved URL, timestamp — discard message text, sender info |
| `$wpdb->prepare()` skipped for product library queries | SQL injection if ASIN or other input used in raw query | Always use `$wpdb->prepare()` for any query with user-supplied or external-supplied data |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| No feedback when Telegram message is received (bot is silent) | User doesn't know if the product was stored | Bot sends a confirmation reply: "Gespeichert: B0CXXX (Produkttitel)" |
| Dropdown shows ASIN only, no product title | User cannot identify which product to insert | Show title (truncated to 50 chars) + ASIN as secondary text in dropdown options |
| `amazon:last` resolves to wrong product if user sent two products rapidly | Confusing — inserted card doesn't match intended product | Dropdown always shows "last received" as first option; user can pick explicitly |
| No loading state in dropdown while fetching product list | Dropdown appears empty; user thinks no products exist | Show "Lade..." placeholder option while `apiFetch` is in flight |
| Webhook registration requires manual curl call | Non-obvious setup step for re-deployment | Add a "Webhook registrieren" button in Plugin Settings that calls setWebhook via WordPress AJAX |

---

## "Looks Done But Isn't" Checklist

- [ ] **Telegram webhook handler:** Returns HTTP 200 _before_ any remote calls complete — verify with Telegram `getWebhookInfo` showing `pending_update_count: 0`.
- [ ] **Secret token verification:** `X-Telegram-Bot-Api-Secret-Token` checked with `hash_equals()`, not `===` (timing-safe comparison).
- [ ] **chat_id filter:** Configured Chat ID is compared; empty Chat ID setting does NOT mean "allow all" — it must block all or require explicit opt-in.
- [ ] **dbDelta schema:** Table verified to exist after activation with `SHOW TABLES LIKE ...`; run the assertion in a test or activation notice.
- [ ] **DB version option:** `mtb_affiliate_db_version` stored in options after schema creation; dbDelta gated behind version check.
- [ ] **ShortURL resolution:** Handles `amzn.to`, `amzn.eu`, direct `amazon.de/dp/ASIN`, and already-resolved `amazon.de` URLs without error.
- [ ] **ASIN fallback:** If ShortURL resolution times out or fails, stores the raw URL and `asin = null`; does not crash the webhook handler.
- [ ] **`amazon:last` in saved content:** No post is saved to DB with `amazon:last` still present in `post_content` — assert this in a save_post test.
- [ ] **Dropdown backward compat:** Existing blocks (no `selectedLibraryProductId` attribute) render identically to before the new attribute was added.
- [ ] **Bot Token server-only:** Grep the built JS files for any occurrence of the Bot Token — must be absent.

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| Telegram retry storm (duplicates) | MEDIUM | Deduplicate by `update_id` in product library table (add UNIQUE constraint); run cleanup query to remove dupes |
| Malformed table from bad dbDelta | MEDIUM | Drop table manually, increment `mtb_affiliate_db_version` option to force re-run, reactivate plugin |
| Bot Token leaked in logs or response | HIGH | Revoke token via BotFather, generate new token, update settings, re-register webhook |
| `amazon:last` token persisted in post content | LOW | Run one-time admin script that scans posts for `amazon:last` in content and flags them for manual review |
| Stale dropdown (missing new products) | LOW | Add refresh button; user clicks it once — no data loss |
| Block validation failed after attribute schema change | MEDIUM | Provide a `deprecated` block version in block.json that maps old attribute structure to new; or run a one-time post content migration |

---

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Telegram timeout / retry storm | Phase 1: Telegram Webhook | Send test message; check `getWebhookInfo` shows 0 pending; check product library for duplicates after 5-minute wait |
| No secret token verification | Phase 1: Telegram Webhook | POST to webhook URL without header — must get 401; with wrong token — must get 401; with correct token — must get 200 |
| dbDelta silent failure | Phase 2: Product Library | Activation test: assert table exists; assert all columns present via `SHOW COLUMNS` |
| dbDelta cannot remove columns | Phase 2: Product Library | Freeze schema before building; document in code any future migration needs |
| ShortURL resolution blocked | Phase 1: Telegram Webhook | Integration test: send `amzn.to` link to webhook; verify ASIN extracted and stored |
| Token collision `amazon:last` vs `amazon:ASIN` | Phase 3: Token Extension | Unit test: post with both `amazon:last` paragraph and existing `amazon:ASIN` block; assert only paragraph is resolved, block untouched |
| Dropdown stale after new product | Phase 4: Editor Enhancements | Manual test: open editor, send Telegram product, click refresh, verify product appears |
| Block attribute backward compat | Phase 4: Editor Enhancements | Load post with old saved block, verify no validation error in editor console |

---

## Sources

- [Telegram Bot API — Webhooks Guide](https://core.telegram.org/bots/webhooks) — official, covers timeout, retry, secret_token
- [Telegram Bot API Reference — setWebhook](https://core.telegram.org/bots/api#setwebhook) — secret_token parameter introduced in Bot API 7.0
- [WordPress Developer Reference — dbDelta()](https://developer.wordpress.org/reference/functions/dbdelta/) — official, formatting requirements
- [WordPress Developer Reference — wp_remote_get()](https://developer.wordpress.org/reference/functions/wp_remote_get/) — timeout and redirection parameters
- [WordPress Developer Reference — wp_safe_remote_get()](https://developer.wordpress.org/reference/functions/wp_safe_remote_get/) — URL validation behaviour
- [Creating and Maintaining Custom Database Tables — Voxfor](https://www.voxfor.com/creating-and-maintaining-custom-database-tables-in-a-wordpress-plugin/) — dbDelta migration patterns, DB version option pattern
- [WordPress Block Editor — @wordpress/api-fetch](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-api-fetch/) — official apiFetch docs
- [React Best Practices for Gutenberg Blocks — rtcamp](https://rtcamp.com/handbook/developing-for-block-editor-and-site-editor/react-best-practices/) — useEffect/useCallback patterns in editor
- [Telegram Bot SDK for PHP — Request Timeouts](https://telegram-bot-sdk.com/docs/advanced/timeouts/) — timeout handling in PHP webhook implementations
- [Secret Token Verification — nguyenthanhluan.com](https://nguyenthanhluan.com/en/glossary/secret_token-for-setwebhook-en/) — X-Telegram-Bot-Api-Secret-Token header pattern
- Existing plugin codebase: `class-mtb-affiliate-plugin.php`, `class-mtb-affiliate-token-scanner.php`, `class-mtb-affiliate-rest-controller.php`, `block.json` — direct inspection of existing patterns that new code must integrate with

---
*Pitfalls research for: WordPress Plugin — Telegram-to-WordPress Affiliate Pipeline (v1.0 milestone)*
*Researched: 2026-03-25*
