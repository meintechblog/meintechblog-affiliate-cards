# Architecture Research

**Domain:** WordPress Plugin — Telegram-to-WordPress Affiliate Pipeline
**Researched:** 2026-03-25
**Confidence:** HIGH (based on direct code inspection of all existing plugin classes and Node-RED flows.json)

---

## Standard Architecture

### System Overview (Existing + New)

```
┌─────────────────────────────────────────────────────────────────────┐
│                        EXTERNAL TRIGGERS                            │
│  ┌────────────────┐  ┌─────────────────┐  ┌──────────────────────┐  │
│  │  Telegram Bot  │  │  Gutenberg Ed.  │  │  WordPress save_post │  │
│  │  (Webhook POST)│  │  (Browser JS)   │  │  (PHP Hook)          │  │
│  └───────┬────────┘  └────────┬────────┘  └──────────┬───────────┘  │
└──────────┼────────────────────┼───────────────────────┼─────────────┘
           │                    │                       │
┌──────────▼────────────────────▼───────────────────────▼─────────────┐
│                        WORDPRESS REST API LAYER                     │
│  ┌─────────────────────┐  ┌──────────────────────────────────────┐  │
│  │  NEW:               │  │  EXISTING:                           │  │
│  │  /mtb-affiliate/v1  │  │  /mtb-affiliate-cards/v1/item        │  │
│  │  /telegram          │  │  (ASIN hydration for editor)         │  │
│  │  /products          │  │                                      │  │
│  │  /products/last     │  │                                      │  │
│  └─────────┬───────────┘  └──────────────────────────────────────┘  │
└────────────┼────────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────────┐
│                        SERVICE LAYER                                │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐  │
│  │  NEW:            │  │  NEW:            │  │  EXISTING:       │  │
│  │  Telegram        │  │  Product         │  │  Amazon Client   │  │
│  │  Webhook Handler │  │  Library Service │  │  Audit Service   │  │
│  └──────────┬───────┘  └────────┬─────────┘  └──────────────────┘  │
└─────────────┼───────────────────┼────────────────────────────────────┘
              │                   │
┌─────────────▼───────────────────▼────────────────────────────────────┐
│                        STORAGE LAYER                                 │
│  ┌─────────────────────────────────────────────────────────────┐     │
│  │  EXISTING: wp_options (plugin settings, audit meta)         │     │
│  │  EXISTING: wp_postmeta (audit state per post)               │     │
│  │  NEW: {prefix}_mtb_affiliate_products (custom table)        │     │
│  └─────────────────────────────────────────────────────────────┘     │
└──────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Status | Responsibility | Communicates With |
|-----------|--------|----------------|-------------------|
| `MTB_Affiliate_Plugin` | Existing | Boot, hook wiring, settings page, save_post | All services |
| `MTB_Affiliate_Settings` | Modified | Store settings incl. bot_token, chat_id | Plugin, RestController |
| `MTB_Affiliate_Block` | Existing | Gutenberg server-render callback | Settings, AmazonClient |
| `MTB_Affiliate_Rest_Controller` | Modified | Routes for item hydration + new product/telegram endpoints | Settings, AmazonClient, ProductLibrary |
| `MTB_Affiliate_Amazon_Client` | Existing | OAuth + ASIN lookup via Creators API | - |
| `MTB_Affiliate_Audit_Service` | Existing | Post content scanning + audit state | - |
| `MTB_Affiliate_Post_Processor` | Modified | Token → block conversion, incl. `amazon:last`/`amazon:lastN` | TokenScanner, ProductLibrary |
| `MTB_Affiliate_Token_Scanner` | Existing | Scan posts for `amazon:ASIN` tokens | - |
| **NEW** `MTB_Affiliate_Telegram_Handler` | New | Parse Telegram webhook payloads, dispatch to processor | ProductLibrary, AmazonClient |
| **NEW** `MTB_Affiliate_Product_Library` | New | CRUD for custom products table via `$wpdb` | $wpdb |
| **NEW** `MTB_Affiliate_Url_Resolver` | New | Resolve amzn.to / amzn.eu shortlinks via wp_remote_get | - |
| `index.js` (Gutenberg block) | Modified | Editor UI: token watcher, hydration, + dropdown picker | WP REST API |

---

## Recommended Project Structure

```
meintechblog-affiliate-cards/
├── meintechblog-affiliate-cards.php     # Entry: require_once chain + boot
├── includes/
│   ├── class-mtb-affiliate-plugin.php         # MODIFIED: wire new services, activate hook for table creation
│   ├── class-mtb-affiliate-settings.php       # MODIFIED: add bot_token, chat_id fields
│   ├── class-mtb-affiliate-rest-controller.php# MODIFIED: register /telegram and /products routes
│   ├── class-mtb-affiliate-block.php          # unchanged
│   ├── class-mtb-affiliate-post-processor.php # MODIFIED: handle amazon:last / amazon:lastN tokens
│   ├── class-mtb-affiliate-token-scanner.php  # unchanged
│   ├── class-mtb-affiliate-amazon-client.php  # unchanged
│   ├── class-mtb-affiliate-audit-service.php  # unchanged
│   ├── class-mtb-affiliate-renderer.php       # unchanged
│   ├── class-mtb-affiliate-badge-resolver.php # unchanged
│   ├── class-mtb-affiliate-title-shortener.php# unchanged
│   ├── class-mtb-affiliate-telegram-handler.php  # NEW
│   ├── class-mtb-affiliate-product-library.php   # NEW
│   └── class-mtb-affiliate-url-resolver.php      # NEW
└── blocks/
    └── affiliate-cards/
        └── index.js                        # MODIFIED: add dropdown picker, amazon:last token support
```

### Structure Rationale

- **All PHP classes stay in `includes/`** — consistent with existing flat-file pattern; no autoloader or namespace changes needed.
- **Three new classes, not one mega-class** — keeps single-responsibility pattern the codebase already follows. TelegramHandler, ProductLibrary, and UrlResolver have no circular dependencies.
- **No new directories** — avoids requiring `require_once` path changes across the plugin.
- **`index.js` modified in-place** — the existing editor script already handles ASIN token watcher and hydration; dropdown picker is a natural extension of the same `useEffect`/`subscribe` pattern.

---

## Architectural Patterns

### Pattern 1: REST Route per Concern (Thin Controller)

**What:** Each new feature gets a dedicated REST route registered via `register_rest_route`, with the route callback delegating immediately to a service class. The controller never contains business logic.

**When to use:** Webhook receiver (POST /telegram) and product queries (GET /products, GET /products/last) both need clean HTTP boundaries with auth/permission callbacks.

**Trade-offs:** Slightly more boilerplate per route; prevents the existing controller from becoming a monolith as new routes land.

**Example:**
```php
// In MTB_Affiliate_Rest_Controller::register_routes()
register_rest_route('mtb-affiliate-cards/v1', '/telegram', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_telegram_webhook'],
    'permission_callback' => '__return_true', // Telegram sends no WP auth; validate secret in handler
]);

register_rest_route('mtb-affiliate-cards/v1', '/products', [
    'methods'             => 'GET',
    'callback'            => [$this, 'get_products'],
    'permission_callback' => [$this, 'can_view_item'], // reuse existing: edit_posts cap
]);
```

### Pattern 2: Activation Hook for Table Creation

**What:** `MTB_Affiliate_Plugin::activate()` (called by `register_activation_hook`) is the correct WordPress entry point for `CREATE TABLE IF NOT EXISTS`. Uses `dbDelta()` for safe idempotent schema upgrades.

**When to use:** Required for the products custom table. Must be triggered on activation, not on every boot — `register_activation_hook` already wires to `MTB_Affiliate_Plugin::activate()`.

**Trade-offs:** Table only created on plugin (re-)activation. If table is missing on an already-active install, expose a "Repair tables" action in the admin settings tab.

**Example:**
```php
// In MTB_Affiliate_Plugin::activate()
MTB_Affiliate_Product_Library::create_table();

// In MTB_Affiliate_Product_Library::create_table()
global $wpdb;
$table = $wpdb->prefix . 'mtb_affiliate_products';
$charset = $wpdb->get_charset_collate();
$sql = "CREATE TABLE IF NOT EXISTS {$table} (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    asin        VARCHAR(10)  NOT NULL,
    title       VARCHAR(500) NOT NULL DEFAULT '',
    detail_url  TEXT         NOT NULL DEFAULT '',
    image_url   TEXT         NOT NULL DEFAULT '',
    received_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY asin (asin),
    KEY received_at (received_at)
) {$charset};";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
```

### Pattern 3: Secret-Token Webhook Validation (No WP Auth)

**What:** Telegram webhooks arrive without WordPress authentication. The handler validates an HMAC signature or secret token header before processing. The secret is stored in plugin settings (never exposed to the client side).

**When to use:** POST /telegram endpoint. Must be validated before any database or Amazon API calls.

**Trade-offs:** A weak secret leaks the endpoint — use Telegram's `X-Telegram-Bot-Api-Secret-Token` header (set when registering the webhook with `setWebhook`) rather than a URL query param.

**Example:**
```php
public function handle_telegram_webhook(\WP_REST_Request $request): \WP_REST_Response {
    $settings = $this->settings->get_all();
    $expectedSecret = $settings['telegram_webhook_secret'] ?? '';
    $receivedSecret = $request->get_header('x-telegram-bot-api-secret-token');

    if ($expectedSecret !== '' && $receivedSecret !== $expectedSecret) {
        return new \WP_REST_Response(null, 403);
    }

    return $this->telegramHandler->handle($request->get_json_params());
}
```

### Pattern 4: `amazon:lastN` Token Resolution via Product Library

**What:** `MTB_Affiliate_Post_Processor::process()` currently handles `amazon:ASIN` tokens. The `amazon:last` / `amazon:last2` / `amazon:lastN` variants require a database lookup before ASIN substitution. The processor receives a `$productLibrary` dependency injected at construction time.

**When to use:** Extend `process()` with a pre-pass that resolves `amazon:last`/`amazon:lastN` tokens to concrete ASINs via `MTB_Affiliate_Product_Library::get_last(N)` before the existing token scan runs.

**Trade-offs:** Makes `PostProcessor` depend on `ProductLibrary`. This is acceptable because the processor is already injected with an `$itemResolver` callback; a similar `$tokenResolver` callable keeps the coupling loose and maintains testability.

**Example:**
```php
// Token patterns to add to TOKEN_PATTERN handling
// amazon:last   → last 1 product from library
// amazon:last2  → last 2 products
// amazon:lastN  → last N products (N = 2..9)

// Resolution in process() pre-pass:
if (preg_match('/^amazon:last(\d*)$/i', $token, $m)) {
    $n = $m[1] !== '' ? (int)$m[1] : 1;
    $asins = ($this->tokenResolver)($n); // calls ProductLibrary::get_last($n)
    // substitute token with the resolved ASIN(s) before main scan
}
```

---

## Data Flow

### Telegram Webhook Flow (New)

```
Telegram Server
    │  POST /wp-json/mtb-affiliate-cards/v1/telegram
    │  Header: X-Telegram-Bot-Api-Secret-Token
    ▼
MTB_Affiliate_Rest_Controller::handle_telegram_webhook()
    │  validate secret token
    ▼
MTB_Affiliate_Telegram_Handler::handle(array $payload)
    │  extract message text / chat_id
    │  guard: chat_id matches allowed_chat_id from settings
    ▼
    ├─ ShortURL? → MTB_Affiliate_Url_Resolver::resolve(string $url): string
    │               wp_remote_get() with redirect follow, returns final URL
    ▼
    parse input: ASIN / Amazon URL / date command / reset / heute
    │
    ├─ ASIN or URL with ASIN found
    │       ▼
    │   MTB_Affiliate_Product_Library::insert(array $product)
    │       ▼
    │   respond via Telegram Bot API (sendMessage) with affiliate URL
    │
    └─ date / tracking-id command
            ▼
        store tracking_id in wp_options (transient or option)
            ▼
        respond with confirmation text
```

### Editor Dropdown Flow (New)

```
Editor mounts affiliate-card block
    ▼
JS: fetch GET /wp-json/mtb-affiliate-cards/v1/products?limit=20
    ▼
PHP: MTB_Affiliate_Rest_Controller::get_products()
    → MTB_Affiliate_Product_Library::get_last(20)
    → returns [{id, asin, title, received_at, ...}]
    ▼
JS: renders <SelectControl> dropdown in InspectorControls panel
    "Aus Bibliothek wählen" (newest first)
    ▼
User selects → block attribute asin updated
    → existing hydrateAffiliateBlock() triggers
```

### amazon:lastN Token Flow (New)

```
Editor: user types "amazon:last2" in paragraph
    ▼
JS subscribe() sees token matching /^amazon:last\d*$/
    → fetch GET /products/last?limit=2
    → get [{asin, title, ...}, {asin, title, ...}]
    → replace paragraph with 2 affiliate-card blocks
      (same flow as existing amazon:ASIN token replacement)

OR

save_post hook fires with amazon:last2 in content
    ▼
MTB_Affiliate_Post_Processor::process()
    → pre-pass resolves amazon:last2 → [ASIN1, ASIN2]
    → inserts as two affiliate-card blocks
    → existing block serialization runs
```

### Key Data Flows Summary

1. **Telegram → DB:** Webhook receives ASIN, `MTB_Affiliate_Url_Resolver` expands shortlinks, `MTB_Affiliate_Product_Library::insert()` stores product, response sent via Telegram Bot API with full affiliate URL.
2. **DB → Editor:** REST GET /products loads recent products into JS dropdown; selection triggers existing hydration flow.
3. **lastN tokens:** Both editor JS (live) and `save_post` PHP (persistence) resolve `amazon:lastN` by calling GET /products/last, then delegate to existing ASIN processing.
4. **Tracking-ID state:** Stored in `wp_options` as `mtb_affiliate_telegram_tracking_id` (not Node-RED context). No session/memory; survives server restarts.

---

## Integration Points

### New Components with Existing Architecture

| Integration | Pattern | Detail |
|-------------|---------|--------|
| `MTB_Affiliate_Plugin::boot()` + TelegramHandler | Dependency injection at construction | Plugin creates `ProductLibrary` and `TelegramHandler`, passes to `RestController` constructor |
| `MTB_Affiliate_Plugin::activate()` + ProductLibrary | Static table creation | `activate()` calls `MTB_Affiliate_Product_Library::create_table()` |
| `MTB_Affiliate_Settings::defaults()` / `sanitize()` | Add new fields | Add `telegram_bot_token`, `telegram_chat_id`, `telegram_webhook_secret` to existing settings array |
| `MTB_Affiliate_Rest_Controller` + new routes | New `register_rest_route` calls in existing `register_routes()` | `/telegram` (POST), `/products` (GET), `/products/last` (GET) |
| `MTB_Affiliate_Post_Processor` + `amazon:lastN` | New pre-pass before existing token scan | Inject `$tokenResolver` callable at construction; falls back to no-op if library absent |
| `index.js` + dropdown | New `useEffect` + REST fetch in `Edit` component | Fetch on block mount, store in component state, render `SelectControl` in `InspectorControls` |
| `index.js` + `amazon:lastN` token | Extend `extractAmazonToken()` regex | Add `/^amazon:last(\d*)$/i` branch in the existing `subscribe()` watcher |

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Telegram Bot API (Webhook inbound) | WordPress REST endpoint receives POST from Telegram | Register webhook URL manually via `setWebhook` API call; URL = `{WP_HOME}/wp-json/mtb-affiliate-cards/v1/telegram` |
| Telegram Bot API (sendMessage outbound) | `wp_remote_post()` to `https://api.telegram.org/bot{TOKEN}/sendMessage` | Token stored in settings; never passed to JS; called from `TelegramHandler` only |
| Amazon amzn.to / amzn.eu shortlinks | `wp_remote_get()` with `redirection => 5` | `MTB_Affiliate_Url_Resolver` wraps this. Equivalent to Node-RED HTTP request node with followRedirects. |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| TelegramHandler ↔ ProductLibrary | Direct method call | `TelegramHandler` owns insert; no events |
| TelegramHandler ↔ AmazonClient | TelegramHandler does NOT call AmazonClient directly | Bot returns affiliate URL built from ASIN + tracking-ID only; no Creators API call at receive time. API lookup happens at render time (existing flow). |
| PostProcessor ↔ ProductLibrary | Callable injected at construction (`$tokenResolver`) | Keeps PostProcessor testable without DB |
| RestController ↔ ProductLibrary | Direct method call | RestController calls `get_last()` for dropdown and `last-N` endpoint |
| JS editor ↔ PHP | WordPress REST API only | No direct PHP function calls from JS; all communication via `fetch()` |

---

## Build Order (Dependency-Driven)

Build in this order to avoid blocking dependencies:

1. **`MTB_Affiliate_Product_Library`** — no dependencies; provides the data layer everything else reads. Includes `create_table()` static method. Unblocks all other new work.

2. **`MTB_Affiliate_Settings` (modified)** — add `telegram_bot_token`, `telegram_chat_id`, `telegram_webhook_secret` fields. Small change, unblocks TelegramHandler and Settings UI.

3. **`MTB_Affiliate_Url_Resolver`** — no dependencies beyond `wp_remote_get`. Unblocks TelegramHandler.

4. **`MTB_Affiliate_Telegram_Handler`** — depends on ProductLibrary, Settings, UrlResolver. Core business logic port from Node-RED flows.json.

5. **`MTB_Affiliate_Rest_Controller` (modified)** — register `/telegram`, `/products`, `/products/last` routes; wire TelegramHandler and ProductLibrary.

6. **`MTB_Affiliate_Plugin` (modified)** — inject ProductLibrary and TelegramHandler into constructor; add `create_table()` call in `activate()`; add Telegram fields to settings form UI.

7. **`MTB_Affiliate_Post_Processor` (modified)** — add `amazon:lastN` pre-pass with injected `$tokenResolver`. Can be built in parallel with steps 4-6.

8. **`index.js` (modified)** — dropdown picker (depends on `/products` REST endpoint) + `amazon:lastN` editor-side token expansion (depends on `/products/last` endpoint). Both depend on steps 3-5 being deployed.

---

## Anti-Patterns

### Anti-Pattern 1: Polling Instead of Webhook

**What people do:** Use `wp_cron` to poll the Telegram `getUpdates` API endpoint periodically.
**Why it's wrong:** WordPress cron is unreliable (fires only on page load), introduces latency, and the Node-RED flow already uses polling only because it ran as a persistent process. WordPress is not a persistent process.
**Do this instead:** Webhook mode exclusively. Register the WordPress REST URL as the Telegram webhook via `setWebhook`. Idempotent and instant.

### Anti-Pattern 2: Storing Bot Token in JavaScript

**What people do:** Pass `telegram_bot_token` to the Gutenberg editor via `wp_localize_script` for status checks or direct API calls from the browser.
**Why it's wrong:** The bot token would be exposed to any logged-in user who opens DevTools. Full control of the bot, including reading all messages, sending messages, and changing webhook URLs.
**Do this instead:** All outbound Telegram API calls go through PHP-only REST callbacks. The JS editor only calls the plugin's own WP REST endpoints.

### Anti-Pattern 3: Using Post Meta for Product Library

**What people do:** Store received products as a custom post type or in post meta for the "current" post.
**Why it's wrong:** Products are not owned by any post — they exist before being placed in a post. The `amazon:last` / `amazon:lastN` tokens require a global ordered list. Post meta cannot express this.
**Do this instead:** Custom table `{prefix}_mtb_affiliate_products` ordered by `received_at DESC`. Simple `$wpdb` queries; no ORM needed.

### Anti-Pattern 4: Node-RED Context Variables in WordPress

**What people do:** Try to replicate Node-RED's `context.trackingId` and `context.lastAsin` using PHP static variables or globals.
**Why it's wrong:** PHP-FPM processes are stateless across requests. A static variable set in request A is gone in request B.
**Do this instead:** Persist tracking-ID state to `wp_options` (`update_option('mtb_affiliate_telegram_tracking_id', $id)`). Persist `lastAsin` to the product library table (last inserted row). Both survive restarts and work across requests.

### Anti-Pattern 5: Re-implementing ShortURL Resolver in JS

**What people do:** Resolve amzn.to links client-side (from the Gutenberg editor) via a CORS fetch to `api.allorigins.win` or similar proxy.
**Why it's wrong:** Introduces third-party dependency, exposes the affiliate workflow, and CORS restrictions make it unreliable. The existing block hydration already uses the WP REST API as a backend proxy.
**Do this instead:** All URL resolution happens in `MTB_Affiliate_Url_Resolver` server-side. The Gutenberg editor never needs to resolve shortlinks — that's a Telegram-receive-time operation only.

---

## Scaling Considerations

This is a single-user, single-site WordPress plugin. Scaling is not a concern. The relevant production constraint is:

| Concern | Approach |
|---------|----------|
| Telegram webhook delivery guarantee | Telegram retries for up to 24h if endpoint returns non-2xx. Ensure handler returns `200 OK` even for unknown formats. |
| Product library growth | Table has indexed `received_at`. At realistic volume (hundreds/year), no pagination or archiving needed. `LIMIT` all queries. |
| Amazon API rate limits | TelegramHandler does NOT call the Amazon Creators API. The existing rate-limit risk in `Block::render()` and `RestController::get_item()` is unchanged. |

---

## Sources

- Direct code inspection: all PHP classes in `includes/` (v0.2.30)
- Direct code inspection: `blocks/affiliate-cards/index.js` (Gutenberg block editor)
- Direct code inspection: `flows.json` (Node-RED Amazon Affiliate Bot flow — reference for business logic to port)
- Telegram Bot API documentation: https://core.telegram.org/bots/api#setwebhook (webhook secret token, X-Telegram-Bot-Api-Secret-Token header)
- WordPress developer docs: `register_rest_route`, `dbDelta`, `wp_remote_get` — standard WordPress patterns, HIGH confidence

---

*Architecture research for: Telegram-to-WordPress Affiliate Pipeline (milestone v1.0)*
*Researched: 2026-03-25*
