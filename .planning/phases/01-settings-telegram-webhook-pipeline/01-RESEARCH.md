# Phase 1: Settings + Telegram Webhook Pipeline - Research

**Researched:** 2026-03-25
**Domain:** WordPress Plugin — Telegram Webhook Receiver + Settings Extension + Tracking-ID Registry Stub
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Bot response format — Claude's Discretion: design a response that includes affiliate URL plus contextual info (ASIN, tracking-ID). Keep useful, not cluttered.
- **D-02:** Error messages in German, consistent with Node-RED bot. Differentiate: ungueltige ASIN, Shortlink nicht aufloesbar, ungueltiges Datum, unbekanntes Format (with help text listing valid inputs).
- **D-03:** Research additional useful error cases beyond what Node-RED handles (e.g., non-.de Amazon domain, timeout, rate limiting).
- **D-04:** wp_options is the pragmatic choice for both trackingId and lastAsin in Phase 1 (before product table exists).
- **D-05:** Tracking-ID state persists across plugin deactivation/reactivation — use wp_options with autoload=false.
- **D-06:** Default tracking-ID follows existing `derive_partner_tag()` logic: `meintechblog-YYMMDD-21` from current date.
- **D-07:** Warn on first use of an unregistered tracking-ID, then suppress until tracking-ID changes.
- **D-08:** "done/ok/angelegt" confirmation flow — user sends "done" → bot saves current tracking-ID to registry and confirms.
- **D-09:** Phase 1 creates the tracking-ID registry table early (since TRID-03/04 depend on it). Table schema owned by Phase 1's activation hook. Phase 2 adds backfill import only.
- **D-10:** Telegram settings live in a dedicated new tab "Telegram Bot" — third tab alongside "Einstellungen" and "Affiliate Audit".
- **D-11:** Webhook status display: simple green/red badge plus optional "Status pruefen" button calling Telegram getWebhookInfo.
- **D-12:** Bot-Token field uses `type="password"`. Chat-ID is plain text (optional). Webhook-Secret is auto-generated or manually settable.
- **D-13:** Webhook endpoint uses `X-Telegram-Bot-Api-Secret-Token` header validation. Permission callback returns true.
- **D-14:** Chat-ID filtering is optional — if configured, only messages from that chat ID are processed. Others silently ignored (return 200).
- **D-15:** Always return HTTP 200 to Telegram, even on errors.

### Claude's Discretion

- Bot response format details (URL + context vs just URL)
- Exact error message wording and additional error cases
- Tracking-ID state storage approach
- Warning trigger logic and frequency
- Webhook status display implementation

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.

</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| SETT-01 | Bot-Token ist in Plugin-Settings konfigurierbar | Settings extension pattern documented below; add to MTB_Affiliate_Settings::defaults() and sanitize() |
| SETT-02 | Chat-ID ist als optionales Feld in Plugin-Settings konfigurierbar | Same pattern as SETT-01; empty string is valid (no filtering) |
| SETT-03 | Webhook-Status (aktiv/inaktiv) wird in Settings angezeigt | Telegram getWebhookInfo API documented; WP admin AJAX pattern for "Status pruefen" button |
| TGBOT-01 | Plugin empfaengt Telegram-Nachrichten via Webhook und validiert Secret-Token | REST route + hash_equals() validation pattern documented |
| TGBOT-02 | Bot antwortet mit fertigem Affiliate-Link (ASIN + aktuelle Tracking-ID) | buildUrl() pattern from flows.json; wp_remote_post sendMessage pattern |
| TGBOT-03 | Bot loest ShortURLs auf (amzn.to / amzn.eu) | wp_remote_get with redirection=10; ASIN regex from resolved URL |
| TGBOT-04 | Bot akzeptiert direkte ASINs und Amazon-Produkt-URLs | ASIN regex `/B0[A-Z0-9]{8}/i` and `/dp/` path extraction, both in flows.json |
| TGBOT-05 | User kann Tracking-ID per Datum setzen (heute, YYMMDD, DD.MM.YY/YYYY) | All date parsing logic extracted from flows.json function node |
| TGBOT-06 | User kann Tracking-ID per "reset" auf Default zuruecksetzen | flows.json reset branch; derive_partner_tag() for default |
| TGBOT-07 | Bot filtert optional nach Chat-ID | chat_id guard pattern; D-14: empty setting = allow all |
| TRID-03 | Bot warnt per Telegram wenn kein passende Tracking-ID in Registry hinterlegt ist | Registry table schema documented; warn-once-per-ID logic using wp_options suppression key |
| TRID-04 | User kann per Telegram-Antwort (done/ok/angelegt) eine neue Tracking-ID als verfuegbar markieren | "done" handler: insert row into registry table; D-08 confirmation flow |

</phase_requirements>

---

## Summary

Phase 1 ports the proven Node-RED Amazon Affiliate Bot (`flows.json`) into the existing WordPress plugin. The implementation adds one new tab to the Settings UI, three new PHP classes (`MTB_Affiliate_Telegram_Handler`, `MTB_Affiliate_Url_Resolver`, `MTB_Affiliate_Tracking_Registry`), and modifies four existing files (`MTB_Affiliate_Settings`, `MTB_Affiliate_Rest_Controller`, `MTB_Affiliate_Plugin`, and the entry-point PHP file). No external dependencies are added — everything builds on WordPress core and PHP core already in use.

The Node-RED flow's `meintechblog-affiliate` function node (line 158 in flows.json) is the authoritative business logic spec. Its exact input patterns, date parsing rules, context variable semantics, error messages, and `buildUrl()` format must be reproduced verbatim in PHP. The two state variables (`context.trackingId`, `context.lastAsin`) map to two `wp_options` keys with `autoload=false`. The ASIN regex in flows.json — `/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i` — restricts to B0-prefixed ASINs only; this differs from the existing plugin's `normalize_asin()` which accepts any 10-char alphanumeric. The handler should use the flows.json regex for extraction but normalize to uppercase before storage.

Phase 1 also creates the tracking-ID registry table early (per D-09), since TRID-03 and TRID-04 are in scope. The table has two columns: `tracking_id` (VARCHAR(50) UNIQUE) and `created_at` (DATETIME). The `done/ok/angelegt` confirmation flow inserts the currently-active tracking-ID into this table. The registry check (TRID-03) queries whether the current tracking-ID exists in this table; it warns once per new tracking-ID, suppressed via a transient or wp_options key.

**Primary recommendation:** Create three new classes, extend three existing ones, follow the flat-class architecture, and treat flows.json as ground truth for all business logic decisions. Return HTTP 200 unconditionally from the webhook endpoint.

---

## Standard Stack

### Core (No New Dependencies)

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| WordPress REST API (`register_rest_route`) | WordPress 5.0+ (built-in) | Webhook endpoint at `/wp-json/mtb-affiliate-cards/v1/telegram` | Already used by `MTB_Affiliate_Rest_Controller` — zero new infrastructure |
| `wp_remote_get` (NOT safe variant) | WordPress core | ShortURL resolution for amzn.to / amzn.eu with redirect following | Safe variant blocks Amazon CDN redirects; `redirection => 10`, `timeout => 5` |
| `wp_remote_post` | WordPress core | Outbound `sendMessage` to Telegram Bot API | Same transport layer used for Amazon API calls |
| `hash_equals()` | PHP core (5.6+) | Constant-time comparison of `X-Telegram-Bot-Api-Secret-Token` | Prevents timing attacks; PHP 8.5 available on dev machine |
| `wp_options` (`get_option` / `update_option`, `autoload=false`) | WordPress core | Store bot_token, chat_id, webhook_secret (in settings array), plus trackingId and lastAsin state | Extends existing `MTB_Affiliate_Settings` infrastructure |
| `$wpdb` + `dbDelta()` | WordPress core | Tracking-ID registry table (`{prefix}mtb_affiliate_tracking_ids`) | Phase 1 creates this table; Phase 2 extends it with backfill |
| `php://input` + `json_decode` | PHP core | Read raw Telegram Update JSON body | `$_POST` is empty for JSON payloads; standard webhook pattern |

### No Alternatives to Consider

The plugin constraint (no external dependencies) eliminates all Telegram Bot SDK libraries. The Bot API surface needed (webhook receive + sendMessage + getWebhookInfo) is simple enough to implement natively.

**Installation:** No `composer require` or `npm install`. All additions are new PHP class files in `includes/` registered via `require_once` in the main plugin file.

---

## Architecture Patterns

### Recommended File Structure for Phase 1

```
includes/
├── class-mtb-affiliate-settings.php           MODIFIED: +telegram_bot_token, telegram_chat_id, telegram_webhook_secret
├── class-mtb-affiliate-rest-controller.php    MODIFIED: +register /telegram route, +handle_telegram_webhook callback
├── class-mtb-affiliate-plugin.php             MODIFIED: +Telegram tab, +instantiate new classes, +activate() creates registry table
├── class-mtb-affiliate-telegram-handler.php   NEW: parse Telegram payload, dispatch, sendMessage
├── class-mtb-affiliate-url-resolver.php       NEW: wp_remote_get ShortURL expansion, ASIN extraction
└── class-mtb-affiliate-tracking-registry.php  NEW: dbDelta table creation, check(), register() for tracking-IDs

meintechblog-affiliate-cards.php               MODIFIED: +require_once for 3 new classes
```

### Pattern 1: Flat-Class Plugin Architecture (Existing — Follow Exactly)

**What:** New classes are instantiated in `MTB_Affiliate_Plugin::__construct()` as private properties, wired in `boot()` via `add_action`. No autoloader, no namespaces, `declare(strict_types=1)` at top of every file.

**Required for Phase 1:**
```php
// In meintechblog-affiliate-cards.php — add after existing requires:
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-tracking-registry.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-url-resolver.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-telegram-handler.php';

// In MTB_Affiliate_Plugin::__construct():
$this->trackingRegistry = new MTB_Affiliate_Tracking_Registry();
$this->urlResolver = new MTB_Affiliate_Url_Resolver();
$this->telegramHandler = new MTB_Affiliate_Telegram_Handler(
    $this->settings,
    $this->urlResolver,
    $this->trackingRegistry
);
// Pass telegramHandler to restController:
$this->restController = new MTB_Affiliate_Rest_Controller(
    $this->settings,
    $this->amazonClient,
    null,
    $this->telegramHandler   // new optional 4th param
);
```

### Pattern 2: REST Route Registration for Webhook (Thin Controller)

**What:** Route registered in `MTB_Affiliate_Rest_Controller::register_routes()`. Permission callback returns `true` unconditionally — Telegram cannot send WordPress auth headers. Secret-token validation happens inside the callback, not in the permission layer.

```php
// In MTB_Affiliate_Rest_Controller::register_routes():
register_rest_route('mtb-affiliate-cards/v1', '/telegram', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_telegram_webhook'],
    'permission_callback' => '__return_true',
]);
```

**The callback pattern (return 200 first, then process):**
```php
public function handle_telegram_webhook(\WP_REST_Request $request): \WP_REST_Response {
    // 1. Validate secret token — before ANY business logic
    $settings        = $this->settings->get_all();
    $expectedSecret  = $settings['telegram_webhook_secret'] ?? '';
    $receivedSecret  = $request->get_header('x-telegram-bot-api-secret-token') ?? '';

    if ($expectedSecret === '' || ! hash_equals($expectedSecret, $receivedSecret)) {
        return new \WP_REST_Response(null, 403);
    }

    // 2. Parse and guard chat_id (fast, no I/O)
    $payload = $request->get_json_params();
    $chatId  = (int) ($payload['message']['chat']['id'] ?? 0);
    $allowedChatId = (int) ($settings['telegram_chat_id'] ?? 0);
    if ($allowedChatId !== 0 && $chatId !== $allowedChatId) {
        return new \WP_REST_Response(null, 200); // silently ignore
    }

    // 3. Delegate to handler (this may call wp_remote_get for ShortURL)
    $this->telegramHandler->handle($payload);

    // 4. Always return 200 — even if handler had errors
    return new \WP_REST_Response(null, 200);
}
```

**Note:** The timeout risk is real for ShortURL resolution. With `timeout => 5` in `wp_remote_get` and single-user low-volume usage, synchronous resolution is acceptable. If p95 latency exceeds 3s, defer via `wp_schedule_single_event` — but this is not required for Phase 1.

### Pattern 3: Settings Extension (Exact Pattern Match)

**What:** Add three new fields to `MTB_Affiliate_Settings`. The settings are serialized as a single array in `wp_options`. The `defaults()`, `sanitize()`, and `get_all()` methods must all be updated consistently.

```php
// In defaults():
'telegram_bot_token'      => '',
'telegram_chat_id'        => '',
'telegram_webhook_secret' => '',

// In sanitize():
$botToken       = trim((string) ($settings['telegram_bot_token'] ?? ''));
$chatId         = trim((string) ($settings['telegram_chat_id'] ?? ''));
$webhookSecret  = trim((string) ($settings['telegram_webhook_secret'] ?? ''));

// Webhook secret: auto-generate if empty (on first save)
if ($webhookSecret === '') {
    $webhookSecret = bin2hex(random_bytes(16)); // 32-char hex
}

return array_merge($sanitized, [
    'telegram_bot_token'      => $botToken,
    'telegram_chat_id'        => $chatId,
    'telegram_webhook_secret' => $webhookSecret,
]);
```

**Security note:** The webhook_secret is auto-generated on first save of the settings form (or on plugin activation). It must be passed as `secret_token` parameter when calling Telegram's `setWebhook`. The user copies this value for their manual curl command (or it is displayed read-only in the UI).

### Pattern 4: Separate wp_options for Transient Bot State

**What:** The bot's mutable state (`trackingId`, `lastAsin`) is stored as separate `wp_options` entries with `autoload=false`, NOT in the main settings array. This matches D-04 and D-05.

```php
// Option names:
define('MTB_TELEGRAM_TRACKING_ID_OPTION', 'mtb_telegram_tracking_id');
define('MTB_TELEGRAM_LAST_ASIN_OPTION',   'mtb_telegram_last_asin');

// Reading state:
$trackingId = get_option(MTB_TELEGRAM_TRACKING_ID_OPTION, '');
if ($trackingId === '') {
    // D-06: derive default from current date using existing method
    $trackingId = (new MTB_Affiliate_Amazon_Client())->derive_partner_tag(
        current_time('Y-m-d H:i:s')
    );
}

// Writing state:
update_option(MTB_TELEGRAM_TRACKING_ID_OPTION, $newTrackingId, false); // false = autoload=no
update_option(MTB_TELEGRAM_LAST_ASIN_OPTION,   $asin,          false);
```

**Why not put these in the main settings array:** The main settings array is saved via WordPress's Settings API (form POST). The bot's tracking state changes via Telegram messages, not form saves. Mixing them would overwrite state on every settings page save.

### Pattern 5: Tracking-ID Registry Table (dbDelta — Strict Formatting)

**What:** Phase 1 creates `{prefix}mtb_affiliate_tracking_ids`. This table is checked by TRID-03 (warn if missing) and written by TRID-04 ("done" command inserts row).

```php
// In MTB_Affiliate_Tracking_Registry::create_table():
global $wpdb;
$table   = $wpdb->prefix . 'mtb_affiliate_tracking_ids';
$charset = $wpdb->get_charset_collate();
$sql     = "CREATE TABLE {$table} (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tracking_id VARCHAR(60) NOT NULL,
  created_at  DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY tracking_id (tracking_id)
) {$charset};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
```

**dbDelta rules that MUST be followed (silent failure if violated):**
- Two spaces between `PRIMARY KEY` and `(id)` — exactly two
- Use `KEY`/`UNIQUE KEY` not `INDEX`
- No `IF NOT EXISTS` inside the CREATE TABLE statement
- Each column definition on its own line
- `$wpdb->get_charset_collate()` at end for utf8mb4 safety

**Table wiring in activate():**
```php
// In MTB_Affiliate_Plugin::activate() — add after existing settings save:
MTB_Affiliate_Tracking_Registry::create_table();
update_option('mtb_affiliate_tracking_registry_db_version', '1.0', false);
```

### Pattern 6: Business Logic Port from flows.json

**What:** The `meintechblog-affiliate` function node in flows.json is the ground truth. Port its logic verbatim to `MTB_Affiliate_Telegram_Handler::dispatch()`.

**Input parsing order (from flows.json — must preserve exactly):**
1. Strip Markdown wrappers: `<URL>`, `(URL)`, `[URL]`
2. `reset` (case-insensitive) → reset trackingId to default
3. `heute` (case-insensitive) → set trackingId to today's YYMMDD
4. `/^[0-9]{6}$/` → YYMMDD date format → validate, set trackingId
5. `/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/` → DD.MM.YY or DD.MM.YYYY → validate, set trackingId
6. Amazon URL regex → extract ASIN with extractAsin(), build affiliate URL
7. `/^B0[A-Z0-9]{8}$/i` → direct ASIN → build affiliate URL
8. Fallback → help text in German

**Critical detail from flows.json — ASIN extraction regex:**
```php
// From flows.json extractAsin() function:
// '/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i'
// NOTE: restricts to B0-prefixed ASINs only (not all 10-char alphanumeric like normalize_asin())
private const ASIN_EXTRACTION_PATTERN = '/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i';
```

**buildUrl() from flows.json:**
```php
// flows.json: `https://www.amazon.de/dp/${asin}?tag=${tracking}`
// NOTE: this is simpler than build_affiliate_detail_url() which adds linkCode/th/psc params
// For the bot reply, use the simple format matching Node-RED output
private function build_affiliate_url(string $asin, string $trackingId): string {
    return "https://www.amazon.de/dp/{$asin}?tag={$trackingId}";
}
```

**Date validation from flows.json (must port to PHP):**
```php
private function is_valid_date(int $day, int $month, string $yearStr): bool {
    $year = strlen($yearStr) === 4 ? (int)$yearStr : 2000 + (int)$yearStr;
    $ts   = mktime(0, 0, 0, $month, $day, $year);
    return $ts !== false
        && (int)date('Y', $ts) === $year
        && (int)date('n', $ts) === $month
        && (int)date('j', $ts) === $day;
}
```

**State update when ASIN received — port flows.json `context.set("lastAsin", asin)` behavior:**
```php
update_option(MTB_TELEGRAM_LAST_ASIN_OPTION, $asin, false);
```

**State NOT updated on tracking-ID commands — when lastAsin exists, also output affiliate URL:**
```php
// From flows.json: when tracking-ID set and lastAsin present, return the affiliate URL immediately
$lastAsin = get_option(MTB_TELEGRAM_LAST_ASIN_OPTION, '');
if ($lastAsin !== '') {
    $text .= "\n\n" . $this->build_affiliate_url($lastAsin, $trackingId);
}
```

### Pattern 7: Telegram sendMessage via wp_remote_post

```php
private function send_message(string $botToken, int $chatId, string $text): void {
    wp_remote_post(
        'https://api.telegram.org/bot' . $botToken . '/sendMessage',
        [
            'body'    => wp_json_encode(['chat_id' => $chatId, 'text' => $text]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5,
        ]
    );
    // Never throw or crash on failure — wp_remote_post may return WP_Error
}
```

### Pattern 8: Settings Tab Addition

**What:** Third tab in `render_settings_page()`. Current tab logic: `current_admin_tab()` returns `'settings'` or `'audit'`. Extend the allowed values to include `'telegram'`.

```php
// In current_admin_tab():
return in_array($tab, ['settings', 'audit', 'telegram'], true) ? $tab : 'settings';

// Nav tab markup to add:
<a class="nav-tab <?php echo $tab === 'telegram' ? 'nav-tab-active' : ''; ?>" href="?page=mtb-affiliate-cards&tab=telegram">Telegram Bot</a>

// In render_settings_page():
<?php elseif ($tab === 'telegram') : ?>
    <?php $this->render_telegram_tab(); ?>
```

**Telegram tab form fields:**
- `telegram_bot_token` → `type="password"`, autocomplete="new-password" (same as client_secret pattern)
- `telegram_chat_id` → `type="text"`, optional
- `telegram_webhook_secret` → `type="text"` with readonly display + regenerate button (or auto-generated on save)
- Webhook status indicator: green/red badge + "Status pruefen" button (calls `getWebhookInfo` via AJAX or form POST)

**Webhook URL display (read-only):**
```php
$webhookUrl = rest_url('mtb-affiliate-cards/v1/telegram');
echo '<input type="text" readonly value="' . esc_attr($webhookUrl) . '" class="regular-text code">';
```

### Pattern 9: TRID-03/04 — Warning + Confirmation Flow

**Warning trigger logic (D-07):**
- When bot processes an ASIN/URL (not a date command), check if current `trackingId` exists in registry
- If NOT in registry AND `mtb_telegram_tracking_warned_{hash}` transient NOT set → send warning message
- Set transient `mtb_telegram_tracking_warned_{hash}` for 30 days to suppress repeat warnings

```php
private function maybe_warn_unregistered_tracking_id(string $trackingId, int $chatId, string $botToken): void {
    $registryCheck = $this->trackingRegistry->exists($trackingId);
    if ($registryCheck) {
        return;
    }

    $suppressKey = 'mtb_trid_warned_' . substr(md5($trackingId), 0, 12);
    if (get_transient($suppressKey) !== false) {
        return; // already warned recently
    }

    $warningText = "Tracking-ID nicht in Registry: {$trackingId}\n"
        . "Sende 'done', 'ok' oder 'angelegt' um sie zu registrieren.";
    $this->send_message($botToken, $chatId, $warningText);
    set_transient($suppressKey, 1, 30 * DAY_IN_SECONDS);
}
```

**"done/ok/angelegt" confirmation flow (D-08):**
```php
// Detect confirmation commands:
$confirmCommands = ['done', 'ok', 'angelegt'];
if (in_array(strtolower($input), $confirmCommands, true)) {
    $trackingId = get_option(MTB_TELEGRAM_TRACKING_ID_OPTION, $this->default_tracking_id());
    $this->trackingRegistry->register($trackingId);
    $this->send_message($botToken, $chatId, "Tracking-ID registriert: {$trackingId}");
    return;
}
```

**When to check for the warning:** Only after successfully processing an ASIN/URL (not on date commands). Reset the suppression transient when the tracking-ID changes, so the warning fires again for the new ID.

### Pattern 10: Webhook Status Check (SETT-03)

**Telegram getWebhookInfo API:**
```
GET https://api.telegram.org/bot{TOKEN}/getWebhookInfo
Response: { "ok": true, "result": { "url": "...", "has_custom_certificate": false, "pending_update_count": 0, ... } }
```

**Implementation approach (WordPress admin AJAX):**
```php
// Register in boot():
add_action('wp_ajax_mtb_check_webhook_status', [$this, 'ajax_check_webhook_status']);

// Handler:
public function ajax_check_webhook_status(): void {
    check_ajax_referer('mtb_webhook_status_check', 'nonce');
    if (! current_user_can('manage_options')) { wp_die(); }

    $settings = $this->settings->get_all();
    $botToken = $settings['telegram_bot_token'] ?? '';
    if ($botToken === '') {
        wp_send_json(['active' => false, 'error' => 'Kein Bot-Token konfiguriert.']);
    }

    $response = wp_remote_get(
        'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo',
        ['timeout' => 10]
    );

    if (is_wp_error($response)) {
        wp_send_json(['active' => false, 'error' => $response->get_error_message()]);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $webhookUrl = $body['result']['url'] ?? '';
    $active = $webhookUrl !== '' && str_contains($webhookUrl, 'telegram');

    wp_send_json([
        'active'   => $active,
        'url'      => $webhookUrl,
        'pending'  => $body['result']['pending_update_count'] ?? 0,
    ]);
}
```

**Simple inline badge (no JS required — shows last-known status):**
Display a green "Aktiv" or red "Inaktiv" badge based on the AJAX call result. If no check has been performed, show "Unbekannt" in grey.

### Anti-Patterns to Avoid

- **Passing bot token to JS or `wp_localize_script`** — token stays server-side only. Never appears in REST response or rendered HTML.
- **Using `wp_safe_remote_get` for ShortURL resolution** — blocks Amazon CDN intermediate redirects. Use `wp_remote_get` only.
- **Returning non-200 HTTP status from webhook on business errors** — Telegram retries on any non-2xx. Always return 200; send error text via `sendMessage`.
- **Checking chat_id as the first security gate** — secret token MUST be validated before reading payload fields like chat_id.
- **Storing webhook_secret in the bot state options** — it belongs in the main settings array (saved via form), not in the transient state options.
- **Calling `wp_schedule_single_event` for Phase 1** — not needed given low message volume; synchronous with 5s timeout is sufficient. Don't add complexity prematurely.
- **Mixing tracking state with Settings API form saves** — state in separate wp_options, settings in the settings array. Never overwrite `mtb_telegram_tracking_id` when user hits "Einstellungen speichern".

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| ShortURL redirect following | Custom curl with header parsing | `wp_remote_get` with `'redirection' => 10` | WordPress HTTP API handles SSL, proxy, redirect chain; `redirection` param does exactly this |
| Constant-time string comparison | Custom loop or `===` | `hash_equals()` (PHP core) | Timing-safe; prevents timing attacks on secret token check |
| Random webhook secret generation | Custom entropy source | `bin2hex(random_bytes(16))` (PHP core 7+) | Cryptographically secure; produces 32 hex chars |
| Settings serialization/retrieval | Custom DB queries | Extend existing `MTB_Affiliate_Settings` | Existing pattern handles defaults, merge, sanitize — identical extension point |
| ASIN validation | New regex | Reuse `normalize_asin()` from `MTB_Affiliate_Rest_Controller` (for checking), but use flows.json `B0[A-Z0-9]{8}` for extraction | Controller method already validated against known patterns |
| Tracking-ID derivation | Rebuild date → ID logic | `MTB_Affiliate_Amazon_Client::derive_partner_tag(string $date)` | Already implements `meintechblog-YYMMDD-21` format exactly |
| Idempotent DB schema creation | Manual `CREATE TABLE IF NOT EXISTS` | `dbDelta()` with correct formatting | `dbDelta` handles existing tables, column additions, preserves data |

---

## Common Pitfalls

### Pitfall 1: Telegram Retry Storm from Slow Webhook Response

**What goes wrong:** Telegram retries up to 8 times if HTTP 200 is not received within ~5s. ShortURL resolution with `wp_remote_get` can take 2-4s on shared hosting, causing duplicates.

**How to avoid:** Return HTTP 200 from `handle_telegram_webhook` BEFORE the TelegramHandler processes anything heavy. The controller calls `$this->telegramHandler->handle($payload)` and then returns 200. Set `'timeout' => 5` on all outbound HTTP calls. On timeout, fall back gracefully (log, send error via sendMessage) rather than blocking.

**Warning signs:** Same Telegram message processed twice; duplicate tracking-ID warnings; `getWebhookInfo` showing `pending_update_count > 0`.

### Pitfall 2: wp_safe_remote_get Blocking Amazon Redirect Chain

**What goes wrong:** `wp_safe_remote_get` validates every URL in the redirect chain. Amazon's CDN/regional redirects include hostnames that fail `wp_http_validate_url`. Function returns `WP_Error` instead of following the chain.

**How to avoid:** Use `wp_remote_get` (not safe variant) in `MTB_Affiliate_Url_Resolver`. Set explicit args including `sslverify => true` (security preserved on the final hop), `timeout => 5`, `redirection => 10`.

**ASIN extraction from redirected URL:**
The resolver must extract ASIN from the **final effective URL**, not the raw response body. `wp_remote_retrieve_header($response, 'x-final-url')` may not be available on all WordPress installs. Safer: call `wp_remote_get` with `redirection => 10`, then get the final URL from `$response['http_response']->get_response_object()->url` or extract ASIN using regex on the last `Location` header. Simplest fallback: extract ASIN from the URL passed to `wp_remote_get` (for cases where the short URL already contains `/dp/ASIN`).

### Pitfall 3: dbDelta Silent Failure from Formatting Violations

**What goes wrong:** dbDelta uses regex-based SQL parsing. Exactly two spaces are required between `PRIMARY KEY` and `(id)`. Using `INDEX` instead of `KEY`, adding `IF NOT EXISTS`, or omitting `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` causes silent no-op.

**How to avoid:** Use the exact template in Pattern 5 above. After calling `dbDelta()`, verify table exists:
```php
$exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
if ($exists !== $table) {
    // log error notice — table creation failed
}
```

### Pitfall 4: Settings Save Overwrites Bot State

**What goes wrong:** If `telegram_tracking_id` and `telegram_last_asin` are in the main settings array, saving the Settings form via WordPress Settings API resets them to their (empty) defaults.

**How to avoid:** Store ONLY the configuration fields (`telegram_bot_token`, `telegram_chat_id`, `telegram_webhook_secret`) in the main settings array. Store bot runtime state in separate `wp_options` keys with `autoload=false` (see Pattern 4 above).

### Pitfall 5: chat_id Empty String vs Zero vs Absent

**What goes wrong:** The `telegram_chat_id` setting is optional (D-14). Empty string = allow all chats. But `(int)'' === 0` and `(int)null === 0`, so the comparison `$chatId !== $allowedChatId` would incorrectly block all messages when the setting is empty.

**How to avoid:**
```php
$allowedChatId = trim($settings['telegram_chat_id'] ?? '');
if ($allowedChatId !== '' && (int)$allowedChatId !== $chatId) {
    return 200; // silently ignore
}
```
Explicit empty-string check before the integer comparison.

### Pitfall 6: ASIN Regex Mismatch Between Plugin and flows.json

**What goes wrong:** The existing `normalize_asin()` pattern is `/^[A-Z0-9]{10}$/` — it accepts ANY 10-char alphanumeric. The flows.json ASIN extraction regex is `/\/(B0[A-Z0-9]{8})\b/i` — it requires B0 prefix. Using the broader pattern may accept non-product ASINs.

**How to avoid:** Use `B0[A-Z0-9]{8}` (case-insensitive, 10 chars total) for extraction from URLs and for direct ASIN input detection. This matches flows.json behavior exactly. After extraction, `strtoupper()` for storage.

### Pitfall 7: Telegram Updates Without message Field

**What goes wrong:** Telegram sends many update types: `edited_message`, `channel_post`, `callback_query`, inline queries, etc. Accessing `$payload['message']['text']` on a callback_query update causes PHP notices or null-handling issues.

**How to avoid:**
```php
$message = $payload['message'] ?? null;
if (! is_array($message)) {
    return; // silently ignore non-message updates — return 200
}
$text    = trim((string)($message['text'] ?? ''));
$chatId  = (int)($message['chat']['id'] ?? 0);
if ($text === '' || $chatId === 0) {
    return; // photo-only message or invalid chat
}
```

### Pitfall 8: Backfill Data Typos in Tracking-ID Registry

**What goes wrong:** The backfill file (`.planning/data/tracking-ids-backfill.txt`) contains entries like `meintechblog-241124-2-21` (double suffix) and potentially `eintechblog-231104-21` (missing 'm'). If these are used as-is for registry lookup, the "does this tracking-ID exist" check may produce false negatives.

**How to avoid:** For Phase 1, the registry table starts empty — typo entries from the backfill file are Phase 2's concern (TRID-02). The Phase 1 `register()` method inserts exactly the string the bot provides (current tracking-ID), no normalization needed.

**Backfill typo documentation (for Phase 2 reference):**
- `meintechblog-241124-2-21` — appears twice in backfill (with and without `-2-`). Both should be imported as-is; they are distinct tracking IDs in the Amazon affiliate system.
- Any `eintechblog-` prefix (missing 'm') — import as-is; the Amazon system tracks these literally.

---

## Code Examples

### Complete URL Resolver (Phase 1 Core)

```php
// Source: PITFALLS.md Pattern 5 + flows.json ShortURL Detector + ShortURL Resolver nodes
final class MTB_Affiliate_Url_Resolver {
    private const ASIN_PATTERN = '/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i';
    private const SHORT_URL_PATTERN = '/https?:\/\/amzn\.(to|eu)\/[^\s]+/i';

    public function is_short_url(string $input): bool {
        return (bool) preg_match(self::SHORT_URL_PATTERN, $input);
    }

    public function resolve(string $shortUrl): ?string {
        $response = wp_remote_get($shortUrl, [
            'timeout'     => 5,
            'redirection' => 10,
            'user-agent'  => 'Mozilla/5.0 (compatible; MTBBot/1.0)',
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            return null; // timeout or network error — caller handles
        }

        // Try to get final URL from response headers or response object
        $finalUrl = '';
        $httpObj = $response['http_response'] ?? null;
        if (is_object($httpObj) && method_exists($httpObj, 'get_response_object')) {
            $finalUrl = (string)($httpObj->get_response_object()->url ?? '');
        }

        // Fallback: Location header from last hop
        if ($finalUrl === '') {
            $finalUrl = (string)(wp_remote_retrieve_header($response, 'location') ?? '');
        }

        return $finalUrl !== '' ? $finalUrl : null;
    }

    public function extract_asin(string $url): ?string {
        if (! preg_match(self::ASIN_PATTERN, $url, $matches)) {
            return null;
        }
        return strtoupper($matches[1]);
    }
}
```

### German Error Messages (Exact Wording from flows.json)

```php
// Source: flows.json meintechblog-affiliate function node
private const ERRORS = [
    'shortlink_fail'  => 'Fehler beim Auflösen des Shortlinks.',
    'no_asin_in_url'  => 'Konnte keine ASIN aus der URL extrahieren.',
    'invalid_yymmdd'  => 'Ungültiges Datum im Format YYMMDD: %s',
    'invalid_date'    => 'Ungültiges Datum: %s',
    'unknown_format'  => "Unbekanntes Format.\n\nGültige Eingaben:\n"
                       . "• ASIN (B0XXXXXXXX)\n"
                       . "• Amazon-URL\n"
                       . "• Shortlink (amzn.to / amzn.eu)\n"
                       . "• Datum YYMMDD\n"
                       . "• Datum DD.MM.YY / DD.MM.YYYY\n"
                       . "• heute\n"
                       . "• reset",
];
```

**Additional error cases to add (D-03 — research finding):**

| Error Case | Trigger | German Message |
|------------|---------|----------------|
| Non-.de Amazon domain | URL contains `amazon.com`, `amazon.co.uk` etc. | `"Nur Amazon.de-Links werden unterstützt."` |
| Webhook secret not configured | Settings has empty webhook_secret | Return 403, log to PHP error log |
| Bot token not configured | Settings has empty bot_token | Return 200 (don't crash), log warning |
| ASIN extraction timeout | wp_remote_get returns WP_Error | `"Shortlink konnte nicht aufgelöst werden (Timeout). Bitte direkte Amazon-URL oder ASIN senden."` |

**Non-.de domain check:**
```php
// Extract domain from resolved URL
if (preg_match('/amazon\.([a-z.]+)\//', $resolvedUrl, $domainMatch)) {
    $tld = $domainMatch[1];
    if ($tld !== 'de') {
        return $this->send_reply($chatId, 'Nur Amazon.de-Links werden unterstützt.');
    }
}
```

### Markdown Wrapper Stripping (from flows.json)

```php
// Source: flows.json meintechblog-affiliate — INPUT robust machen section
$input = trim($input);
$input = preg_replace('/^<(.+)>$/', '$1', $input);   // <https://...>
$input = preg_replace('/^\((.+)\)$/', '$1', $input); // (https://...)
$input = preg_replace('/^\[(.+)\]$/', '$1', $input); // [https://...]
```

### Default Tracking-ID Derivation (Reusing Existing Code)

```php
// Source: includes/class-mtb-affiliate-amazon-client.php
// derive_partner_tag('2026-03-25 10:00:00') → 'meintechblog-260325-21'
private function default_tracking_id(): string {
    $client = new MTB_Affiliate_Amazon_Client();
    return $client->derive_partner_tag(current_time('Y-m-d H:i:s'));
}
```

---

## State of the Art

| Old Approach (Node-RED) | WordPress Port | Notes |
|-------------------------|----------------|-------|
| `context.trackingId` (Node-RED flow context) | `wp_options('mtb_telegram_tracking_id', autoload=false)` | Survives server restarts; persists across deactivation (D-05) |
| `context.lastAsin` (Node-RED flow context) | `wp_options('mtb_telegram_last_asin', autoload=false)` | Same survival semantics |
| `global.defaultTrackingId` (Inject node) | `MTB_Affiliate_Amazon_Client::derive_partner_tag(current_date)` | D-06: dynamic default from current date, not a hardcoded string |
| Node-RED HTTP Request node (follow redirects) | `wp_remote_get` with `redirection => 10` | Equivalent semantics; `timeout => 5` maps to Node-RED default timeout |
| Node-RED Telegram receiver/sender nodes | WordPress REST API + `wp_remote_post` to Bot API | No polling; webhook push model |
| `allowedChatId = 297934858` (hardcoded) | `$settings['telegram_chat_id']` (configurable, optional) | D-14: configurable, empty = allow all |

---

## Environment Availability

| Dependency | Required By | Available | Version | Notes |
|------------|-------------|-----------|---------|-------|
| PHP | All PHP code | Yes | 8.5.3 | `hash_equals()` available since PHP 5.6; `random_bytes()` since PHP 7.0 |
| WordPress REST API | TGBOT-01 | Yes (plugin already uses it) | 5.0+ | `register_rest_route` already in use |
| Telegram Bot API | TGBOT-01..07 | External — verified at runtime | 9.5 | HTTPS to api.telegram.org; no install needed |
| `wp_remote_get` / `wp_remote_post` | TGBOT-03, SETT-03 | Yes (WordPress core) | Built-in | Already used by Amazon client |
| `dbDelta()` | TRID-03/04 registry table | Yes (WordPress core) | Built-in | Requires `require_once ABSPATH . 'wp-admin/includes/upgrade.php'` |

**Missing dependencies:** None — all required functionality is available in the current environment.

**Live environment caveat (from STATE.md blockers):** Verify `wp_remote_get` with `redirection => 10` successfully follows `amzn.to` chain on the live meintechblog.de hosting environment. The developer machine check is not sufficient; document a manual verification step in the phase plan using a real `amzn.to` test URL.

---

## Validation Architecture

**Test framework:** The project uses plain PHP test files in `tests/` run directly with `php`. No PHPUnit, no Composer test runner. Each file stubs WordPress functions, instantiates classes, and `exit(1)` on failure.

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Plain PHP CLI (no PHPUnit) |
| Test directory | `tests/` |
| Quick run | `php tests/test-settings.php && php tests/test-rest-controller.php` |
| Full suite | `for f in tests/test-*.php; do php "$f" || exit 1; done` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | File |
|--------|----------|-----------|------|
| SETT-01 | telegram_bot_token stored/retrieved in settings | unit | `tests/test-settings.php` (extend existing) |
| SETT-02 | telegram_chat_id stored/retrieved, empty = valid | unit | `tests/test-settings.php` |
| SETT-03 | getWebhookInfo AJAX returns active/inactive | manual | Manual browser test post-deploy |
| TGBOT-01 | Webhook returns 403 with wrong secret, 200 with correct | unit | `tests/test-telegram-handler.php` (new) |
| TGBOT-02 | Bot reply contains affiliate URL with tracking-ID | unit | `tests/test-telegram-handler.php` |
| TGBOT-03 | amzn.to URL resolves to ASIN | unit | `tests/test-url-resolver.php` (new) |
| TGBOT-04 | Direct ASIN and amazon.de/dp/ASIN URLs both extract ASIN | unit | `tests/test-url-resolver.php` |
| TGBOT-05 | heute/YYMMDD/DD.MM.YYYY all set correct tracking-ID | unit | `tests/test-telegram-handler.php` |
| TGBOT-06 | reset reverts to default tracking-ID | unit | `tests/test-telegram-handler.php` |
| TGBOT-07 | Unauthorized chat_id returns 200 silently, no processing | unit | `tests/test-telegram-handler.php` |
| TRID-03 | Warning sent on first use of unregistered tracking-ID | unit | `tests/test-tracking-registry.php` (new) |
| TRID-04 | "done" command inserts current tracking-ID into registry | unit | `tests/test-tracking-registry.php` |

### Sampling Rate

- **Per implementation commit:** `php tests/test-settings.php` (existing, fast)
- **Per wave merge:** `for f in tests/test-*.php; do php "$f" || exit 1; done`
- **Phase gate:** Full suite green before marking phase complete

### Wave 0 Gaps (New Test Files Needed)

- [ ] `tests/test-telegram-handler.php` — covers TGBOT-01..07 (stub wp_remote_post, wp_options)
- [ ] `tests/test-url-resolver.php` — covers TGBOT-03..04 (stub wp_remote_get with mock redirect chain)
- [ ] `tests/test-tracking-registry.php` — covers TRID-03..04 (stub $wpdb, dbDelta, get_transient/set_transient)

---

## Open Questions

1. **ShortURL final URL extraction from wp_remote_get response**
   - What we know: `wp_remote_get` with `redirection => 10` follows redirects. The final URL may be in `$response['http_response']->get_response_object()->url` depending on WordPress version and HTTP transport.
   - What's unclear: The exact response structure for the final effective URL is transport-dependent (Curl vs Streams). No official wp_remote_get documentation guarantees a `final_url` field.
   - Recommendation: In `MTB_Affiliate_Url_Resolver::resolve()`, extract ASIN from the raw redirect chain by parsing the last `Location` header OR by applying the ASIN regex directly to all intermediate URLs. Multiple fallback strategies are safer than relying on a single response field.

2. **Webhook "Status pruefen" — getWebhookInfo needs valid bot token**
   - What we know: `getWebhookInfo` call requires the bot token to be configured in settings. If bot token is empty, the AJAX handler must respond gracefully.
   - What's unclear: Whether to call getWebhookInfo on Settings page load (auto) or only on button click.
   - Recommendation: Button-click only (WordPress admin AJAX via `wp_ajax_` hook). Display last-known status from a cached option. This avoids an external HTTP call on every settings page load.

3. **Telegram updates without `message` field**
   - What we know: Telegram sends `edited_message`, `callback_query`, channel posts, etc. in addition to `message`.
   - What's unclear: Whether meintechblog_bot is expected to receive only private messages, or if channel/group messages are possible.
   - Recommendation: In the handler, check for `$payload['message']` first. If absent, return early (silently discard, return 200). No need to handle other update types in Phase 1.

---

## Sources

### Primary (HIGH confidence)

- `flows.json` (direct inspection) — authoritative business logic: input patterns, regex, error messages, state variables, buildUrl format, date validation algorithm
- `includes/class-mtb-affiliate-settings.php` (direct inspection) — settings extension pattern: defaults(), sanitize(), get_all()
- `includes/class-mtb-affiliate-rest-controller.php` (direct inspection) — REST route registration, permission_callback, can_view_item pattern
- `includes/class-mtb-affiliate-plugin.php` (direct inspection) — boot() hook wiring, render_settings_page() tab pattern, current_admin_tab() allowlist, activate() pattern
- `includes/class-mtb-affiliate-amazon-client.php` (direct inspection) — derive_partner_tag() for default tracking-ID derivation
- `includes/class-mtb-affiliate-token-scanner.php` (direct inspection) — TOKEN_PATTERN `/^(?:amazon:)?([A-Z0-9]{10})$/` — Phase 1 handler must NOT interfere with this
- `meintechblog-affiliate-cards.php` (direct inspection) — require_once chain, activation hook pattern, plugin entry point
- `tests/` (direct inspection) — plain PHP test pattern (stub WP functions, exit(1) on failure), no PHPUnit
- `.planning/research/PITFALLS.md` — Telegram retry storm, dbDelta formatting, wp_safe_remote_get blocking, secret token pattern (HIGH confidence, sourced from official docs)
- `.planning/research/ARCHITECTURE.md` — class structure, data flow, build order (HIGH confidence, based on code inspection)
- `.planning/research/STACK.md` — technology decisions, installation pattern (HIGH confidence, based on official WordPress and Telegram docs)

### Secondary (MEDIUM confidence)

- `.planning/data/tracking-ids-backfill.txt` (direct inspection) — 201 tracking IDs; `meintechblog-241124-2-21` is a legitimate double-suffix ID, not a typo; all other entries follow standard format
- Telegram Bot API 9.5 (from STACK.md, sourced against https://core.telegram.org/bots/api) — sendMessage, setWebhook, getWebhookInfo, X-Telegram-Bot-Api-Secret-Token header

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — No new libraries; everything is WordPress core and PHP core already in use. Zero uncertainty.
- Architecture: HIGH — Based on direct code inspection of all existing plugin classes. New class structure directly mirrors existing patterns.
- Business logic: HIGH — flows.json is the authoritative spec and was directly inspected. Input patterns, regexes, error messages, and state semantics are fully documented.
- Pitfalls: HIGH — Telegram retry behavior, wp_safe_remote_get blocking, and dbDelta formatting are verified against official docs in prior research (PITFALLS.md).
- Tracking-ID registry: HIGH — Simple two-column table with UNIQUE constraint; standard dbDelta pattern.
- ShortURL final URL extraction: MEDIUM — The exact field in wp_remote_get response for final effective URL is transport-dependent. Multiple fallback strategies documented.

**Research date:** 2026-03-25
**Valid until:** 2026-06-25 (stable WordPress + Telegram APIs; 90 days)
