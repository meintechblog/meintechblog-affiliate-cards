# Feature Research

**Domain:** WordPress Plugin — Telegram-to-WordPress Affiliate Pipeline
**Researched:** 2026-03-25
**Confidence:** HIGH (existing Node-RED flows are the authoritative spec; WordPress patterns are well-established)

---

## Context: What Already Exists

This is a subsequent milestone. The following is already shipped and must not be re-built:

- Affiliate Card Gutenberg Block with server-side rendering (`meintechblog/affiliate-cards`)
- `amazon:ASIN` token replacement in the editor (paragraph-to-block live substitution)
- Amazon Creators API integration with OAuth 2.0 and ASIN lookup
- Partner-tag derivation from post date (`meintechblog-YYMMDD-21`)
- Affiliate Audit System
- Plugin Settings UI (`MTB_Affiliate_Settings`) with `client_id`, `client_secret`, `marketplace`, `badge_mode`, `cta_label`
- REST Controller infrastructure (`MTB_Affiliate_Rest_Controller`, `/mtb-affiliate-cards/v1/item`)

All new features build on top of this foundation without breaking changes.

---

## Feature Landscape

### Table Stakes (Users Expect These)

Features the workflow cannot function without. Missing any of these = the Node-RED replacement is incomplete.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Telegram Webhook endpoint (WordPress REST) | Bot cannot receive messages without it; replaces Node-RED receiver node | MEDIUM | Register via `register_rest_route`, `permission_callback => __return_true`, read body with `file_get_contents('php://input')`. Must be HTTPS (Telegram requirement). WordPress REST routes are publicly accessible by default — security comes from chat_id filter, not auth. |
| Short URL resolution (`amzn.to` / `amzn.eu`) | ~50% of Telegram shares are shortlinks from mobile; bot returns garbage without this | MEDIUM | Use `wp_remote_get` with `redirection => 5` (WordPress HTTP API — no cURL dependency). Extract final URL from `wp_remote_retrieve_header($response, 'location')` or follow chain. Already fully documented in the Node-RED flow. |
| ASIN extraction from Amazon URLs | Core function of the bot — without ASIN there is no affiliate card | LOW | Regex already proven in Node-RED: `/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i`. Direct reuse from `flows.json`. |
| Bot reply via Telegram sendMessage API | User needs confirmation the message was processed correctly | LOW | `wp_remote_post("https://api.telegram.org/bot{TOKEN}/sendMessage", [...])`. Synchronous, fires after processing. |
| Tracking-ID management (`heute`, `YYMMDD`, `DD.MM.YY`, `reset`) | Existing Node-RED workflow depended on per-session state; WordPress must replicate it | MEDIUM | Node-RED used `context` (per-flow memory). WordPress equivalent: `wp_options` table with `get_option`/`update_option`. Single-user flow, no concurrency issue. Store `mtb_telegram_tracking_id` and `mtb_telegram_last_asin`. |
| Chat-ID filter (allowlist) | Security: prevents strangers from injecting products into the library | LOW | Configurable in Plugin Settings. Compare incoming `message.chat.id` (integer) to stored setting. Silently drop if mismatch — do not reply to unauthorized senders. |
| Product Library DB table | Products must persist across requests and be accessible to the editor | MEDIUM | Custom `{prefix}mtb_affiliate_products` table via `$wpdb->query(dbDelta(...))` on activation. Columns: `id`, `asin`, `title`, `image_url`, `detail_url`, `tracking_id`, `created_at`. Use `$wpdb->prepare()` for all queries. |
| `amazon:last` token support in the editor | The core value proposition — insert the most recent product without typing an ASIN | LOW | Extend existing token-replacement logic in `index.js`. When `amazon:last` detected, call new REST endpoint `/mtb-affiliate-cards/v1/library?limit=1` to get latest ASIN, then run existing hydration. |
| Bot Token + Chat-ID in Plugin Settings | Without these in settings, deployment is hardcoded and untransferable | LOW | Extend `MTB_Affiliate_Settings` defaults with `telegram_bot_token` and `telegram_chat_id`. The bot token must never be output to any frontend script or public REST response. |

### Differentiators (Competitive Advantage)

Features that make this meaningfully better than the Node-RED flow it replaces.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| `amazon:lastN` token (e.g. `amazon:last2`, `amazon:last3`) | Insert second or third most-recent product — useful when writing comparison posts; impossible in Node-RED | LOW | Parameterize the library endpoint: `/mtb-affiliate-cards/v1/library?offset=N&limit=1`. Editor token pattern: `/^amazon:last(\d+)?$/`. Integer offset replaces `amazon:last` = offset 0. |
| Product Library admin page with list table | Inspect, audit, and delete products received via Telegram without touching the DB | HIGH | Use `WP_List_Table` (core WordPress pattern). Columns: ASIN, Title (truncated), Tracking-ID, Date. Bulk-delete action. No inline edit needed — ASIN and metadata are immutable once received. Register via `add_menu_page` or as sub-page under the existing plugin settings. |
| Dropdown picker in the block's InspectorControls | Pick any library product by name without memorizing ASINs | MEDIUM | Add `SelectControl` to existing `InspectorControls` PanelBody in `index.js`. Populate via `wp.apiFetch` call to `/mtb-affiliate-cards/v1/library` on panel open. Selecting a product calls existing `hydrateAffiliateBlock` with the chosen ASIN. Newest products first (`ORDER BY created_at DESC`). |
| Affiliate link echoed back in bot reply | Telegram message instantly contains the ready-to-use affiliate URL — same UX as Node-RED | LOW | Replicate Node-RED output: `https://www.amazon.de/dp/{ASIN}?tag={tracking_id}`. Reply with this URL so the user can share it independently of the WordPress post if needed. |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Telegram Bot command handling (`/start`, `/help`, `/list`) | Seems natural for a bot | Commands require a BotFather config change, add a dispatch layer, and have zero value for a single-user workflow where the operator knows the input format | Keep plain-text input only. Send a help message as the fallback reply for unrecognized input (already in Node-RED). |
| Webhook auto-registration via `setWebhook` on plugin activate | Removes a setup step | WordPress activation runs in admin context, SSL cert may not be trusted by Telegram, timing is unreliable, and secrets should not be transmitted during automated hooks | Document a one-time manual `setWebhook` call using the plugin's webhook URL. Display the URL prominently in settings. |
| Multi-user / multi-chat-ID support | Future-proofing | Adds complexity to session state (whose `lastAsin`? whose `trackingId`?), conflates tracking IDs across users, and requires a per-user state store | Keep `allowed_chat_id` as a single value. Revisit only if multi-user becomes an explicit requirement. |
| Telegram polling fallback | Would work without a public HTTPS URL | WordPress cannot run a blocking loop. Polling in a scheduled WP-Cron job introduces 1–5 minute latency — unacceptable for a <30-second workflow | Webhook only. Require HTTPS. Document this constraint explicitly. |
| Product search by title in library | Looks useful in a large library | The library is single-user and grows slowly (~1-5 products/day). Dropdown + newest-first is sufficient. Full-text search adds a DB index and a search REST parameter for negligible practical gain | Sort by `created_at DESC`. Limit dropdown to 50 items. |
| Per-product manual ASIN correction in admin | Edge case where the bot misidentified an ASIN | Rare enough that it's not worth a form + REST endpoint. The operator can re-send the corrected URL via Telegram | Delete the wrong entry via bulk-delete; re-send the correct URL. |

---

## Feature Dependencies

```
[Bot Token + Chat-ID in Settings]
    └──required by──> [Telegram Webhook endpoint]
                          └──required by──> [Short URL resolution]
                          └──required by──> [ASIN extraction]
                          └──required by──> [Bot reply via sendMessage]
                          └──required by──> [Tracking-ID management]
                          └──required by──> [Product Library DB table (write)]

[Product Library DB table]
    └──required by──> [amazon:last token support]
    └──required by──> [amazon:lastN token support]
    └──required by──> [Dropdown picker in InspectorControls]
    └──required by──> [Product Library admin page]

[amazon:last token support]
    └──enhances──> [amazon:lastN token support]
        (same code path, parameterized)

[Existing hydrateAffiliateBlock (index.js)]
    └──reused by──> [amazon:last token support]
    └──reused by──> [Dropdown picker in InspectorControls]

[Existing MTB_Affiliate_Settings]
    └──extended by──> [Bot Token + Chat-ID in Settings]
        (add fields to defaults() and sanitize() — no new class needed)
```

### Dependency Notes

- **Webhook endpoint requires Bot Token in Settings:** The endpoint reads the token from `MTB_Affiliate_Settings::get_all()` to call `sendMessage`. Token must be present before webhook is registered.
- **Product Library table must exist before any write:** Create via `dbDelta` in `register_activation_hook`. For updates to an existing install, use `dbDelta` guarded by a stored version comparison (`mtb_affiliate_db_version` option).
- **Editor tokens require a library REST endpoint:** `amazon:last` and the dropdown picker both need `/mtb-affiliate-cards/v1/library`. Build this before wiring the JS.
- **Tracking-ID state is write-path only:** The Telegram bot writes `mtb_telegram_tracking_id` to `wp_options`. The editor never reads or needs it — editor tokens only care about ASINs in the product library.

---

## MVP Definition

### Launch With (v1)

The minimum set to fully replace Node-RED and make the <30-second workflow possible end-to-end.

- [ ] Bot Token + Chat-ID stored in Plugin Settings — prerequisite for everything
- [ ] Telegram Webhook REST endpoint — receives messages, verifies chat_id
- [ ] Short URL resolution — handles `amzn.to` / `amzn.eu` shares from mobile
- [ ] ASIN extraction + affiliate link generation — core bot output
- [ ] Bot reply via `sendMessage` — confirms processing
- [ ] Tracking-ID management (`heute`, `YYMMDD`, `DD.MM.YY(YY)`, `reset`) — per-session state via `wp_options`
- [ ] Product Library DB table — persists all received products
- [ ] `amazon:last` token in editor — the primary editor shortcut
- [ ] Dropdown picker in InspectorControls — visual ASIN selection for non-token workflow

### Add After Validation (v1.x)

Add once the core pipeline is running and daily use confirms value.

- [ ] `amazon:lastN` tokens — add when writing comparison posts becomes a regular pattern
- [ ] Product Library admin page (`WP_List_Table`) — add when the library grows enough that ad-hoc inspection is needed; until then, the dropdown shows the same data

### Future Consideration (v2+)

- [ ] Webhook URL display in Settings with one-click copy — only worth building if setup UX becomes a pain point after first deploy
- [ ] Product Library search/filter — defer until library exceeds ~200 products, which at current posting rate is 1–2 years out

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Bot Token + Chat-ID in Settings | HIGH | LOW | P1 |
| Telegram Webhook endpoint | HIGH | MEDIUM | P1 |
| Short URL resolution | HIGH | MEDIUM | P1 |
| ASIN extraction | HIGH | LOW | P1 |
| Bot reply (sendMessage) | HIGH | LOW | P1 |
| Tracking-ID management | HIGH | MEDIUM | P1 |
| Product Library DB table | HIGH | MEDIUM | P1 |
| `amazon:last` editor token | HIGH | LOW | P1 |
| Dropdown picker in block | HIGH | MEDIUM | P1 |
| `amazon:lastN` tokens | MEDIUM | LOW | P2 |
| Product Library admin page | MEDIUM | HIGH | P2 |
| Webhook URL display in Settings | LOW | LOW | P3 |

**Priority key:**
- P1: Must have — workflow is broken without it
- P2: Should have — adds measurable convenience once P1 is stable
- P3: Nice to have — ergonomic, not functional

---

## Implementation Pattern Notes

### Telegram Webhook in WordPress

The standard pattern for a WordPress plugin receiving external POST webhooks is:

```php
register_rest_route('mtb-affiliate-cards/v1', '/telegram/webhook', [
    'methods'             => 'POST',
    'callback'            => [$this, 'handle_webhook'],
    'permission_callback' => '__return_true',  // Telegram cannot send WP auth headers
]);
```

Security is enforced inside `handle_webhook`: parse JSON body, extract `message.chat.id`, compare to stored `telegram_chat_id`. Silently return `200 OK` to all requests (including rejected ones) — Telegram retries on non-200 responses, causing log spam.

The webhook URL for `setWebhook` is: `https://{site}/wp-json/mtb-affiliate-cards/v1/telegram/webhook`

### Short URL Resolution in PHP (no cURL dependency)

WordPress HTTP API handles redirects natively:

```php
$response = wp_remote_get($short_url, ['redirection' => 5, 'timeout' => 10]);
$final_url = wp_remote_retrieve_header($response, 'location');
// If empty, use wp_remote_retrieve_effective_url() or parse redirect manually.
```

Using `wp_remote_get` instead of raw cURL keeps the plugin compatible with WordPress hosting environments that proxy HTTP through WordPress's HTTP transport layer.

### Tracking-ID State Storage

Node-RED used per-flow `context` variables (`context.trackingId`, `context.lastAsin`). The WordPress equivalent is `wp_options`:

```php
update_option('mtb_telegram_tracking_id', $tracking_id, false);  // autoload=false
update_option('mtb_telegram_last_asin', $asin, false);
```

This is single-user, non-concurrent — no locking needed. `autoload=false` prevents these from loading on every page request.

### Editor Dropdown Picker Pattern

The existing block already uses `wp.apiFetch` indirectly (via native `fetch`). For the library dropdown, the established Gutenberg pattern is:

```js
// In AffiliateCardsEdit, inside InspectorControls PanelBody:
// Use wp.apiFetch on panel mount, populate a SelectControl.
// On selection change: set ASIN attribute → triggers existing useEffect hydration.
```

This reuses `hydrateAffiliateBlock` without modification — the dropdown just sets the ASIN attribute the same way the TextControl does today.

### `amazon:last` Token Extension

The existing token scanner in `index.js` matches `/^amazon:([A-Z0-9]{10})$/`. Extend to also match `amazon:last` and `amazon:lastN`:

1. Detect `amazon:last` or `amazon:last2` in paragraph content
2. Call `/mtb-affiliate-cards/v1/library?limit=1&offset={N-1}` (authenticated with `X-WP-Nonce`)
3. Receive ASIN from response
4. Feed into existing `hydrateAffiliateBlock` flow — no other changes needed

---

## Sources

- Node-RED `flows.json` in project root — authoritative source for all bot logic, input formats, and state management patterns (HIGH confidence)
- [Telegram Bot API — Webhook Guide](https://core.telegram.org/bots/webhooks) — webhook requirements, SSL, port constraints (HIGH confidence)
- [WordPress REST API — Creating Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) — `register_rest_route` patterns (HIGH confidence)
- [WordPress — Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/) — `dbDelta` pattern (HIGH confidence)
- [Gutenberg SelectControl docs](https://developer.wordpress.org/block-editor/reference-guides/components/select-control/) — dropdown component API (HIGH confidence)
- [Using useSelect in Gutenberg with REST API](https://permanenttourist.ch/2023/01/using-useselect-in-gutenberg-to-fetch-data-from-the-rest-api/) — editor data-fetch patterns (MEDIUM confidence)
- [WP_List_Table example](https://github.com/pmbaldha/WP-Custom-List-Table-With-Database-Example) — admin list table pattern (MEDIUM confidence)
- Existing `blocks/affiliate-cards/index.js` — direct inspection of token replacement and hydration architecture (HIGH confidence)
- Existing `includes/class-mtb-affiliate-rest-controller.php` — direct inspection of REST controller patterns (HIGH confidence)

---

*Feature research for: Telegram-to-WordPress Affiliate Pipeline (meintechblog-affiliate-cards v1.0)*
*Researched: 2026-03-25*
