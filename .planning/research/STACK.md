# Stack Research

**Domain:** WordPress Plugin — Telegram Bot Webhook + Product Library + Editor Extensions
**Researched:** 2026-03-25
**Confidence:** HIGH (all core claims verified against official docs or WordPress core)

---

## Scope

This document covers ONLY the NEW stack additions required for v1.0. The existing plugin
stack (PHP 8.0+, Gutenberg blocks, Amazon Creators API client, REST controller, Settings)
is already validated and is not re-researched here.

---

## Recommended Stack

### Core Technologies (New)

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Telegram Bot API | 9.5 (March 2026) | Webhook receive + sendMessage | Official API, no library wrapper needed — plugin constraint forbids external deps anyway |
| WordPress REST API (`register_rest_route`) | WordPress 5.0+ (built-in) | Expose webhook endpoint at `/wp-json/mtb-affiliate-cards/v1/telegram` | Already used by existing `MTB_Affiliate_Rest_Controller` — zero new infrastructure |
| `$wpdb` + `dbDelta()` | WordPress core (built-in) | Custom table for product library | Plugin constraint: no ORM. `dbDelta` handles CREATE + ALTER safely, preserves data |
| WordPress HTTP API (`wp_remote_get`) | WordPress core (built-in) | ShortURL resolution (amzn.to / amzn.eu) | Abstracts cURL/streams, follows redirects up to N hops, already used in codebase |

### Supporting Patterns (No New Libraries)

| Pattern | Built On | Purpose | When to Use |
|---------|----------|---------|-------------|
| `hash_equals()` secret token check | PHP core | Verify `X-Telegram-Bot-Api-Secret-Token` header — constant-time comparison prevents timing attacks | Every incoming webhook request before JSON decode |
| `php://input` + `json_decode` | PHP core | Read raw Telegram Update JSON from POST body | Webhook handler — `$_POST` is empty for JSON payloads |
| `preg_match` ASIN regex | PHP core | Extract ASIN from resolved Amazon URL | After ShortURL redirect chain is followed |
| `wp_options` (`get_option` / `update_option`) | WordPress core | Store bot_token, chat_id, tracking ID state | Extends existing settings infrastructure — no new storage layer |
| `dbDelta()` version guard | WordPress core | Run schema migration only when `mtb_product_library_db_version` option changes | `plugins_loaded` hook — safe re-runs, data preserved |

---

## Integration Points with Existing Plugin

| New Component | Hooks Into | How |
|---------------|------------|-----|
| `MTB_Affiliate_Telegram_Controller` | `rest_api_init` via `MTB_Affiliate_Plugin::boot()` | Same pattern as `MTB_Affiliate_Rest_Controller::register_routes()` |
| `MTB_Affiliate_Product_Library` | `register_activation_hook` | Add to `MTB_Affiliate_Plugin::activate()` to run `dbDelta` on install/upgrade |
| Product library REST endpoint | `rest_api_init` | GET `/products` and GET `/products/last` — used by Gutenberg Dropdown Picker |
| `amazon:last` / `amazon:lastN` tokens | `MTB_Affiliate_Post_Processor` | Resolve these special tokens by querying the product library before the existing ASIN pipeline |
| Bot settings (bot_token, chat_id) | `MTB_Affiliate_Settings` | Add fields to existing settings array and settings page form |

---

## Telegram Webhook Handling — No Library Needed

The plugin constraint ("keine externen Dependencies") eliminates all PHP Telegram Bot
libraries. The Bot API is simple enough to handle natively:

**Incoming webhook (POST body):**
```php
$secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if ( ! hash_equals( $expected_secret, $secret ) ) {
    wp_die( '', '', [ 'response' => 403 ] );
}
$update = json_decode( file_get_contents( 'php://input' ), true );
```

**Outgoing sendMessage:**
```php
wp_remote_post(
    'https://api.telegram.org/bot' . $bot_token . '/sendMessage',
    [
        'body'    => wp_json_encode( [ 'chat_id' => $chat_id, 'text' => $text ] ),
        'headers' => [ 'Content-Type' => 'application/json' ],
        'timeout' => 5,
    ]
);
```

Both use only WordPress HTTP API + PHP core. No Composer, no autoloader.

**Webhook URL pattern:** `https://meintechblog.de/wp-json/mtb-affiliate-cards/v1/telegram`

Telegram requires HTTPS on ports 443, 80, 88, or 8443. The existing meintechblog.de
WordPress install already satisfies this.

**Permission callback:** Return `true` unconditionally — authentication is the secret token
header check inside the callback, not a WordPress user capability check.

---

## ShortURL Resolution — wp_remote_get with Redirect Following

`amzn.to` and `amzn.eu` are HTTP 301 redirect chains that resolve to a full Amazon URL
containing the ASIN in the `/dp/ASIN` path segment.

```php
$response = wp_remote_get( $short_url, [
    'redirection' => 5,   // follow up to 5 hops (amzn.to is typically 2)
    'timeout'     => 8,
] );
$final_url = wp_remote_retrieve_header( $response, 'x-final-url' );
// Fallback: check response URL from WP_HTTP or parse Location headers manually
```

After resolution, extract ASIN with:
```php
preg_match( '~/dp/([A-Z0-9]{10})~i', $resolved_url, $m );
$asin = $m[1] ?? null;
```

This handles: `amazon.de/dp/ASINXXXXXXXX`, `amazon.de/gp/product/ASINXXXXXXXX`, and
`amazon.de/.../ASINXXXXXXXX/` path formats. ASINs are always 10 alphanumeric characters
starting with B0 for physical products.

**Caveat:** `wp_safe_remote_get()` validates every redirect URL — use it when the input URL
comes from untrusted sources (Telegram message). It blocks private IPs and non-HTTP(S)
schemes, which is correct security posture here.

---

## Custom Table Schema (Product Library)

```sql
CREATE TABLE {$wpdb->prefix}mtb_affiliate_products (
  id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  asin       VARCHAR(10) NOT NULL,
  title      VARCHAR(500) NOT NULL DEFAULT '',
  image_url  VARCHAR(1000) NOT NULL DEFAULT '',
  price      VARCHAR(50) NOT NULL DEFAULT '',
  url        VARCHAR(2000) NOT NULL DEFAULT '',
  received_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY asin (asin),
  KEY received_at (received_at)
) {$wpdb->get_charset_collate()};
```

Key `dbDelta` rules that must be followed exactly (breaks silently if violated):
- Two spaces between `PRIMARY KEY` and `(id)`
- Use `KEY` not `INDEX`
- Each field on its own line
- No backticks around field names
- Include `$wpdb->get_charset_collate()` for utf8mb4 safety

`BIGINT(20)` display width is ignored on MySQL 8.0.17+ — this is correct, use it anyway
for WordPress compatibility with older MySQL installs.

---

## Alternatives Considered

| Recommended | Alternative | Why Not |
|-------------|-------------|---------|
| Native `wp_remote_*` + `json_decode` for Telegram | `php-telegram-bot/core` (Packagist) | Plugin constraint: no external dependencies; the Bot API surface needed is tiny (getUpdates, sendMessage) |
| `$wpdb` + `dbDelta` for product storage | WordPress Custom Post Type (`affiliate_product`) | Products are not editorial content — no need for revisions, taxonomies, or the `wp_posts` bloat; direct table is faster to query for `amazon:lastN` |
| WordPress REST API for webhook endpoint | Dedicated PHP file (`telegram-webhook.php`) | Already have REST infrastructure; consistent with existing `register_routes()` pattern; benefits from WordPress bootstrap (settings access, `$wpdb`) |
| `wp_options` for bot settings | Separate DB table for settings | One setting group, two fields — options are the right tool; already used by `MTB_Affiliate_Settings` |
| `wp_safe_remote_get` for ShortURL | PHP `curl_exec` directly | WordPress HTTP API abstracts transport, handles SSL, works when cURL is absent; `wp_safe_remote_get` adds SSRF protection appropriate for user-supplied URLs |

---

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Any Packagist/Composer dependency | Plugin constraint explicitly forbids external deps | Native WordPress + PHP core functions throughout |
| Long-polling via WP-Cron | WordPress cron is not real-time; fires on page loads; completely unreliable for <30s Telegram response goal | Webhook only — Telegram pushes updates instantly |
| `wp_insert_post` for product storage | Adds post meta, revisions, term relationships — enormous overhead for a simple product record | `$wpdb->insert()` into custom table |
| `wp_options` for per-product storage | Options table is a key-value store; scanning it for "last N products" requires LIKE queries or serialized arrays | Custom table with `ORDER BY received_at DESC LIMIT N` |
| Storing bot_token in JS/block attributes | Telegram bot tokens must never reach the client | Server-side only: `MTB_Affiliate_Settings::get_all()` on server-rendered REST callback |

---

## Stack Patterns by Context

**If ShortURL resolves but ASIN regex fails:**
- Log and skip — do not block the webhook response; Telegram retries if your endpoint times out
- Respond 200 immediately, process asynchronously if needed

**If bot_token is empty when webhook fires:**
- Return 200 (not 4xx) — Telegram will retry on non-200 responses, causing thundering herd
- Silently discard and log

**If `amazon:last` is used but product library is empty:**
- Treat identically to unknown ASIN — skip token replacement, leave `amazon:last` literal in content
- Consistent with existing behavior for unresolvable `amazon:ASIN` tokens

---

## Version Compatibility

| Component | Compatible With | Notes |
|-----------|-----------------|-------|
| Telegram Bot API 9.5 | All Bot API versions back to 6.0+ | `setWebhook` + `sendMessage` are stable, unchanged API surface |
| `dbDelta()` | WordPress 3.3+ | Available on all supported WordPress versions |
| `wp_remote_get` `redirection` param | WordPress 2.7+ | Default is 5; set explicitly to document intent |
| `hash_equals()` | PHP 5.6+ | Available in all PHP 8.x installs — plugin requires PHP 8.0+ |
| `register_rest_route` | WordPress 4.4+ | Already verified by existing `MTB_Affiliate_Rest_Controller` |

---

## Installation

No `npm install` or `composer require` commands. All additions are new PHP class files
dropped into `includes/` and registered in `meintechblog-affiliate-cards.php`:

```
includes/class-mtb-affiliate-telegram-controller.php   (webhook handler + sendMessage)
includes/class-mtb-affiliate-product-library.php       (dbDelta setup + CRUD)
includes/class-mtb-affiliate-shorturl-resolver.php     (wp_safe_remote_get + ASIN regex)
```

Wire-up in `MTB_Affiliate_Plugin`:
- `require_once` the three new files in `meintechblog-affiliate-cards.php`
- Instantiate in `MTB_Affiliate_Plugin::__construct()`
- Register routes in `boot()` via `rest_api_init`
- Call `MTB_Affiliate_Product_Library::maybe_upgrade_schema()` in `activate()` and `plugins_loaded`

---

## Sources

- [Telegram Bot API official docs](https://core.telegram.org/bots/api) — verified Bot API 9.5, setWebhook secret_token, sendMessage — HIGH confidence
- [Telegram Webhook Guide](https://core.telegram.org/bots/webhooks) — port requirements, HTTPS mandate, SSL verification — HIGH confidence
- [WordPress dbDelta reference](https://developer.wordpress.org/reference/functions/dbdelta/) — schema rules, version tracking pattern — HIGH confidence
- [WordPress REST API: Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) — register_rest_route, permission_callback — HIGH confidence
- [wp_remote_get reference](https://developer.wordpress.org/reference/functions/wp_remote_get/) — redirection parameter default and behavior — HIGH confidence
- [wp_safe_remote_get reference](https://developer.wordpress.org/reference/functions/wp_safe_remote_get/) — SSRF protection via wp_http_validate_url — HIGH confidence
- [ASIN regex gist (GreenFootballs)](https://gist.github.com/GreenFootballs/6731201fafc67ecc9322ccb4a7977018) — `/dp/` path segment extraction pattern — MEDIUM confidence (community source, pattern verified manually against Amazon URL structures)

---

*Stack research for: Telegram-to-WordPress Affiliate Pipeline (v1.0 new features only)*
*Researched: 2026-03-25*
