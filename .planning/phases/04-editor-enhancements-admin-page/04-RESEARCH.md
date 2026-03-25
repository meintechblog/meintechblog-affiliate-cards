# Phase 4: Editor Enhancements + Admin Page - Research

**Researched:** 2026-03-25
**Domain:** WordPress Gutenberg block editor (JS, no build step), WP_List_Table PHP admin UI
**Confidence:** HIGH

## Summary

Phase 4 delivers two independent capabilities: (1) a product-picker dropdown in the Gutenberg sidebar that hydrates the affiliate card block the same way manual ASIN entry does, and (2) a WordPress admin page listing all stored products with bulk-delete. A third success criterion — live editor resolution of `amazon:last` / `amazon:last2` tokens in the ASIN text field — is **already implemented** and does not need to be planned.

The block editor work is pure JavaScript with no build step. The existing `index.js` already imports `SelectControl`, `InspectorControls`, `useEffect`, `fetch`, and `wp.data`, so the dropdown can be wired in without adding dependencies. The REST endpoint `GET /mtb-affiliate-cards/v1/products` already returns products sorted newest-first with all fields the dropdown needs. The hydration pathway (`hydrateAffiliateBlock`) is already tested and known-good.

The admin page work is pure PHP. The plugin already registers one admin page under Settings (`add_options_page`). PLIB-04 adds a second top-level or submenu page with a `WP_List_Table` subclass listing all products with bulk-delete. The existing plugin boot architecture (`register_settings_page` / `render_settings_page`) is the direct pattern to follow. The note in STATE.md (feedback_admin_menu_style.md) says the user prefers a dedicated top-level WP admin menu item with submenus over Settings subpages.

**Primary recommendation:** Add dropdown to `InspectorControls` in `index.js` by fetching products on mount, rendering a `SelectControl`, and calling the existing `updateItem('asin', ...)` path on change. Add a separate `MTB_Affiliate_Product_Library_Page` PHP class with a `WP_List_Table` subclass hooked via `add_menu_page` or `add_submenu_page`.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| EDIT-03 | Affiliate Card Block hat Dropdown-Picker mit gespeicherten Produkten (neueste oben) | REST `/products` endpoint exists, returns all fields; `SelectControl` already imported in `index.js` |
| EDIT-04 | Dropdown-Auswahl hydrated den Block identisch zu manueller ASIN-Eingabe | `updateItem('asin', value)` + `useEffect([currentAsin])` already drives full hydration; dropdown just needs to call the same setter |
| PLIB-04 | Admin-Seite zeigt alle gespeicherten Produkte als WP_List_Table | `MTB_Affiliate_Product_Library->get_recent()` provides data; `WP_List_Table` subclass + `add_menu_page` is the standard WP pattern |
</phase_requirements>

---

## Already Implemented — Do Not Re-Plan

**Success Criterion #3 is DONE** (commit 6831f66):

- `amazon:last` and `amazon:heute` / `amazon:gestern` in the editor's ASIN field are handled by `installAmazonParagraphTrigger()` in `blocks/affiliate-cards/index.js`.
- `SHORTHAND_PATTERN = /^amazon:(last|heute|today|gestern|yesterday)$/i` detects these tokens in any **paragraph block** that loses focus.
- When detected, the paragraph block is replaced with fully-hydrated affiliate card blocks.
- `amazon:last2` is handled via the `/products/last2` REST route (the regex `/products/last(?P<n>\d*)` matches `last`, `last2`, `last3`, etc.).

The planner must mark EDIT-03 / EDIT-04 as the only JS work needed; criterion #3 is verification only.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `wp.components.SelectControl` | bundled with WP | Dropdown picker in Gutenberg sidebar | Already imported in `index.js`; standard Gutenberg UI component |
| `wp.element.useEffect` / `useState` | bundled with WP | React hooks for product list state in block editor | Already used throughout `index.js` |
| `window.fetch` | browser native | Load products from REST API on block mount | Already used in the block for hydration |
| `WP_List_Table` | WordPress core | Admin list table with pagination, sorting, bulk actions | WordPress standard; built in, no extra install |
| PHP 8.x / WordPress 6.x | environment | Server-side admin page rendering | Already the runtime |

### No New Dependencies Required

The phase requires zero new npm packages, zero new Composer packages, and zero new WordPress plugin dependencies. All needed tools exist in the WordPress global scope (`window.wp.*`) and WordPress core PHP.

### Installation
```bash
# Nothing to install — all dependencies are pre-existing
```

---

## Architecture Patterns

### Pattern 1: Dropdown Picker in Gutenberg InspectorControls (EDIT-03 / EDIT-04)

**What:** Fetch product list on block mount using `useEffect`, store in component state with `useState`, render as `SelectControl` in the existing `PanelBody`. On selection, call `updateItem('asin', selectedAsin)` — exactly what the ASIN TextControl does today.

**When to use:** Any Gutenberg block needing to pick from a server-side list without a build step.

**Pattern:**

```javascript
// Inside AffiliateCardsEdit, alongside existing useState/useRef:
const useState = element.useState;

// State for product picker
const [ products, setProducts ] = element.useState( [] );
const [ productsLoaded, setProductsLoaded ] = element.useState( false );

// Fetch on mount
useEffect( function () {
    if ( productsLoaded ) { return; }
    var root = ( window.wpApiSettings && window.wpApiSettings.root )
        ? window.wpApiSettings.root.replace( /\/+$/, '' )
        : '/wp-json';
    var headers = {};
    if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
        headers[ 'X-WP-Nonce' ] = window.wpApiSettings.nonce;
    }
    window.fetch( root + '/' + PRODUCTS_ENDPOINT + '?limit=50', {
        credentials: 'same-origin',
        headers: headers
    } )
        .then( function ( r ) { return r.json(); } )
        .then( function ( data ) {
            if ( Array.isArray( data ) ) {
                setProducts( data );
            }
            setProductsLoaded( true );
        } )
        .catch( function () { setProductsLoaded( true ); } );
}, [] );

// In the InspectorControls PanelBody, after the existing TextControl for ASIN:
products.length > 0 && el( SelectControl, {
    label: i18n.__( 'Aus Bibliothek wählen', 'meintechblog-affiliate-cards' ),
    value: '',
    options: [ { label: '— Produkt auswählen —', value: '' } ].concat(
        products.map( function ( p ) {
            return {
                label: ( p.title || p.asin ) + ' (' + p.asin + ')',
                value: p.asin
            };
        } )
    ),
    onChange: function ( value ) {
        if ( value ) {
            updateItem( 'asin', value.trim().toUpperCase() );
        }
    }
} )
```

**Key insight on hydration identity:** `updateItem('asin', value)` triggers `useEffect([currentAsin])` which resets all hydration state and calls `hydrateAffiliateBlock()`. This is exactly what happens when a user types a new ASIN — so EDIT-04 is automatically satisfied by using the same setter.

**block.json attribute change:** No new attribute is needed. The dropdown writes to the existing `items[0].asin` path. However, STATE.md has this blocker note: *"Confirm `block.json` attribute addition with `"default": ""` produces no Gutenberg validation warning against existing saved blocks before shipping."* Since no attribute is added here, this blocker does not apply.

### Pattern 2: WP_List_Table Admin Page (PLIB-04)

**What:** A separate PHP class registers a new admin menu entry and renders a `WP_List_Table` subclass showing all products with bulk-delete.

**Standard WP_List_Table subclass structure:**

```php
// includes/class-mtb-affiliate-product-library-list-table.php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MTB_Affiliate_Product_Library_List_Table extends WP_List_Table {
    private MTB_Affiliate_Product_Library $library;

    public function __construct( MTB_Affiliate_Product_Library $library ) {
        parent::__construct( [
            'singular' => 'produkt',
            'plural'   => 'produkte',
            'ajax'     => false,
        ] );
        $this->library = $library;
    }

    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox">',
            'asin'        => 'ASIN',
            'title'       => 'Titel',
            'received_at' => 'Empfangen',
        ];
    }

    public function get_bulk_actions(): array {
        return [ 'delete' => 'Löschen' ];
    }

    // prepare_items(), column_cb(), column_default(), process_bulk_action() ...
}
```

**Admin page registration in MTB_Affiliate_Plugin:**

```php
// In boot():
add_action( 'admin_menu', [ $this, 'register_product_library_page' ] );

// New method:
public function register_product_library_page(): void {
    add_submenu_page(
        'mtb-affiliate-cards',          // parent slug of existing settings page
        'Produkt-Bibliothek',
        'Produkt-Bibliothek',
        'manage_options',
        'mtb-affiliate-product-library',
        [ $this, 'render_product_library_page' ]
    );
}
```

**User preference (from project memory `feedback_admin_menu_style.md`):** User prefers dedicated top-level WP admin menu item with submenus over Settings subpages. The existing settings page is registered under `add_options_page` (which puts it under Settings > Affiliate Card). PLIB-04 should add the Produkt-Bibliothek as a submenu under the existing plugin menu — but since the existing page is under Settings, a new `add_menu_page` for the plugin with the existing settings and the new library page as submenus is the preferred architecture. This is a design decision the planner must lock.

**Simplest approach that satisfies PLIB-04 without full menu restructure:** Add `add_submenu_page` pointing to the parent `mtb-affiliate-cards` slug. This works because `add_options_page` registers the page under the `options-general.php` parent, not under a custom slug. To add a submenu to a Settings-registered page, use `options-general.php` as parent:

```php
add_submenu_page(
    'options-general.php',
    'Produkt-Bibliothek',
    'Produkt-Bibliothek',
    'manage_options',
    'mtb-affiliate-product-library',
    [ $this, 'render_product_library_page' ]
);
```

OR restructure the entire plugin to use `add_menu_page` (one top-level) with submenus (Einstellungen, Audit, Telegram, Produkt-Bibliothek). Given user preference, the planner should decide. Research recommends the top-level menu approach for cleaner navigation.

### Bulk Delete Implementation

**WP_List_Table bulk delete requires:**
1. A nonce on the form: `wp_nonce_field('bulk-produkte')` (plural matches constructor)
2. `process_bulk_action()` called before `prepare_items()`
3. Capability check: `current_user_can('manage_options')`
4. Delete method on `MTB_Affiliate_Product_Library`: `delete_by_ids(array $ids): int` using `$wpdb->query` with `WHERE id IN (...)`

```php
public function delete_by_ids( array $ids ): int {
    global $wpdb;
    if ( $ids === [] ) { return 0; }
    $ids = array_map( 'intval', $ids );
    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $result = $wpdb->query(
        $wpdb->prepare( "DELETE FROM {$this->table_name()} WHERE id IN ({$placeholders})", ...$ids )
    );
    return is_int( $result ) ? $result : 0;
}
```

### Anti-Patterns to Avoid

- **Loading products every render cycle:** The product list fetch should use `useEffect(fn, [])` (empty deps = mount only). Without the empty array, Gutenberg will re-fetch on every state change, creating an infinite loop with `setProducts`.
- **Uncontrolled SelectControl value:** The dropdown must use `value: ''` (always reset to placeholder) after selection, not `value: currentAsin`. The purpose is a "picker" action, not a persistent selection UI — after picking, the ASIN TextControl shows the value. Keeping the dropdown at `''` prevents confusion.
- **WP_List_Table without require_once:** `WP_List_Table` is not autoloaded outside admin context. Always guard with `require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'` at the top of the admin page render method.
- **Missing `_wpnonce` on bulk action form:** WordPress will reject the bulk action silently without a nonce. The WP_List_Table base class injects `_wpnonce` only if `display()` wraps in a proper `<form>` with `method="post"`.
- **Registering REST routes for products without nonce:** The `/products` endpoint uses `can_view_item()` which requires `edit_posts` capability. The JS fetch must include `X-WP-Nonce: wpApiSettings.nonce` — already done in the existing hydration pattern.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Admin list table with sorting, pagination, bulk | Custom HTML table | `WP_List_Table` | Handles nonces, pagination state, bulk action routing, screen options |
| Product delete via custom AJAX | Custom wp_ajax_ handler | WP_List_Table bulk action POST + `$wpdb->query` | WP_List_Table POST flow handles nonce + redirect after action |
| Product picker option labels | Custom fetch + DOM manipulation | `SelectControl` with mapped `options` array | React-managed, accessible, keyboard navigable |

---

## Common Pitfalls

### Pitfall 1: `useEffect` Dependency Array on Product Fetch
**What goes wrong:** Using `useEffect(fn)` (no deps array) refetches products on every render. Using `useEffect(fn, [currentAsin])` refetches every time ASIN changes, which hammers the REST API.
**Why it happens:** Gutenberg re-renders the edit component frequently.
**How to avoid:** Use `useEffect(fn, [])` with a mounted guard: `const [ productsLoaded, setProductsLoaded ] = useState(false)`. Check `productsLoaded` at the top of the effect.
**Warning signs:** Network tab shows repeated GET `/products` requests while typing in any field.

### Pitfall 2: `SelectControl` onChange Triggering ASIN Hydration Loop
**What goes wrong:** If the dropdown `value` prop is bound to `item.asin`, selecting a product sets the dropdown to show the ASIN, then ASIN change triggers hydration, which updates `items`, which re-renders with the new `item.asin` — the dropdown shows it as selected. Then if user clicks again (even on blank option) it may re-trigger hydration with `''`.
**Why it happens:** Two-way binding between dropdown and ASIN attribute.
**How to avoid:** Keep dropdown `value` always `''`. The dropdown is a write-only action picker, not a reflection of current state.
**Warning signs:** Console shows rapid `Produktdaten werden geladen...` notices after a single dropdown selection.

### Pitfall 3: WP_List_Table Class Not Found
**What goes wrong:** Fatal PHP error when rendering admin page outside full WP admin request lifecycle.
**Why it happens:** `WP_List_Table` requires `wp-admin/includes/class-wp-list-table.php` which is only loaded in admin contexts.
**How to avoid:** Guard at top of render method: `if (!class_exists('WP_List_Table')) { require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php'; }`
**Warning signs:** White screen on admin page load; PHP fatal in debug log.

### Pitfall 4: Bulk Delete Nonce Mismatch
**What goes wrong:** Bulk delete silently does nothing, or throws `die(-1)`.
**Why it happens:** WP_List_Table generates nonce `bulk-{plural}` where plural comes from the constructor. If `process_bulk_action()` verifies against a different nonce name, it fails.
**How to avoid:** Use WP's built-in `check_admin_referer('bulk-' . $this->_args['plural'])` in `process_bulk_action()`. The constructor plural is `'produkte'`, so nonce is `bulk-produkte`.
**Warning signs:** Checkbox selected, delete clicked, page refreshes but rows remain.

### Pitfall 5: `products/last2` Token Not Resolving in Editor
**What goes wrong:** `amazon:last2` typed in the block's ASIN TextControl does NOT resolve as a shorthand token. The `SHORTHAND_PATTERN` only matches `last|heute|today|gestern|yesterday`.
**Why it happens:** This is expected behavior. `amazon:last2` in the ASIN field is NOT a shorthand token — it's treated as an invalid ASIN (not `[A-Z0-9]{10}`), so no hydration fires. The live token behavior only works in **paragraph blocks** (the `installAmazonParagraphTrigger` function), not in the ASIN TextControl.
**Clarification:** Success Criterion #3 says `amazon:last` in "the editor's ASIN field" but examining the code, it actually works by detecting `amazon:last` written as a *standalone paragraph block*, not by typing it into the InspectorControls TextControl. The existing commit (6831f66) implements this correctly. No change needed.
**Warning signs:** If a spec says "type amazon:last in the ASIN field" — that's not how the current implementation works. Verify the spec interpretation before implementing anything extra.

---

## Code Examples

### Verified Pattern: Fetch Products from REST in Gutenberg Block Edit

```javascript
// Source: existing index.js hydration pattern (same credentials approach)
// Place inside AffiliateCardsEdit function, after existing hook declarations

const useState = element.useState;
const [ products, setProducts ] = useState( [] );
const [ productsLoaded, setProductsLoaded ] = useState( false );

useEffect( function () {
    if ( productsLoaded ) { return; }
    var root = ( window.wpApiSettings && window.wpApiSettings.root )
        ? window.wpApiSettings.root.replace( /\/+$/, '' )
        : '/wp-json';
    var headers = {};
    if ( window.wpApiSettings && window.wpApiSettings.nonce ) {
        headers[ 'X-WP-Nonce' ] = window.wpApiSettings.nonce;
    }
    window.fetch( root + '/mtb-affiliate-cards/v1/products?limit=50', {
        credentials: 'same-origin',
        headers: headers
    } )
        .then( function ( r ) { return r.ok ? r.json() : []; } )
        .then( function ( data ) {
            setProducts( Array.isArray( data ) ? data : [] );
            setProductsLoaded( true );
        } )
        .catch( function () { setProductsLoaded( true ); } );
}, [] );
```

### Verified Pattern: WP_List_Table Subclass for Products

```php
// Source: WordPress Codex / core pattern — standard WP_List_Table implementation
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class MTB_Affiliate_Product_Library_List_Table extends WP_List_Table {
    private MTB_Affiliate_Product_Library $library;

    public function __construct( MTB_Affiliate_Product_Library $library ) {
        parent::__construct( [
            'singular' => 'produkt',
            'plural'   => 'produkte',
            'ajax'     => false,
        ] );
        $this->library = $library;
    }

    public function get_columns(): array {
        return [
            'cb'          => '<input type="checkbox">',
            'asin'        => __( 'ASIN', 'meintechblog-affiliate-cards' ),
            'title'       => __( 'Titel', 'meintechblog-affiliate-cards' ),
            'received_at' => __( 'Empfangen', 'meintechblog-affiliate-cards' ),
        ];
    }

    protected function column_cb( $item ): string {
        return '<input type="checkbox" name="product_ids[]" value="' . (int) $item['id'] . '">';
    }

    protected function column_default( $item, $column_name ): string {
        return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
    }

    public function get_bulk_actions(): array {
        return [ 'delete' => __( 'Löschen', 'meintechblog-affiliate-cards' ) ];
    }

    public function process_bulk_action(): void {
        if ( $this->current_action() !== 'delete' ) { return; }
        check_admin_referer( 'bulk-produkte' );
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $ids = array_map( 'intval', (array) ( $_POST['product_ids'] ?? [] ) );
        if ( $ids !== [] ) {
            $this->library->delete_by_ids( $ids );
        }
    }

    public function prepare_items(): void {
        $this->process_bulk_action();
        $this->_column_headers = [ $this->get_columns(), [], [] ];
        $this->items = $this->library->get_recent( 200 );
    }
}
```

### Verified Pattern: REST Endpoint Response Shape (already existing)

```
GET /wp-json/mtb-affiliate-cards/v1/products?limit=50

Response: [
  {
    "id": "42",
    "asin": "B0XXXXXXXX",
    "title": "Produkttitel",
    "detail_url": "https://...",
    "image_url": "https://...",
    "received_at": "2026-03-25 10:00:00"
  },
  ...
]
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `wp.ajax` for REST-like calls | `window.fetch` + `wpApiSettings.root` | WP 4.7+ | Direct REST API use; already in use in `index.js` |
| `wp.element.Component` class-based | Functional components + hooks | WP 5.8+ / React 16.8 | `useState`, `useEffect` pattern already used in `index.js` |
| WP_List_Table with manual pagination SQL | WP_List_Table + `set_pagination_args()` | Core | Standard; for Phase 4, simple `get_recent(200)` with no pagination is acceptable for v1.0 |

---

## Open Questions

1. **Top-level menu vs. Settings submenu for Produkt-Bibliothek**
   - What we know: User prefers own WP admin menu entry (from project memory). Current settings page uses `add_options_page` (under Settings). The roadmap says "Admin Page" generically.
   - What's unclear: Should Phase 4 restructure the existing Affiliate Card settings from under Settings to a top-level menu entry, or just add the library page as a second Settings submenu?
   - Recommendation: Planner should decide and lock. Minimum viable: add second `add_submenu_page` under `options-general.php` with slug `mtb-affiliate-product-library`. Preferred per user memory: create one `add_menu_page('mtb-affiliate-cards-menu', ...)` with Einstellungen and Produkt-Bibliothek as submenus. This is a one-plan decision.

2. **Product count limit for dropdown picker**
   - What we know: `get_recent(20)` is default; `get_recent(limit: 100)` is the REST API max. A dropdown with 100 entries is usable; 500+ would be unwieldy.
   - Recommendation: Fetch with `?limit=50` for the dropdown. This is sufficient for the author workflow.

3. **Success Criterion #3 interpretation ("ASIN field" vs. "paragraph block")**
   - What we know: The existing implementation resolves `amazon:last` when typed as a standalone paragraph block (not in the ASIN TextControl). The success criterion wording says "in the editor's ASIN field."
   - Recommendation: Treat as already satisfied by current paragraph-block trigger mechanism. The planner should add a verification task, not an implementation task, for this criterion.

---

## Environment Availability

Step 2.6: SKIPPED — Phase 4 is pure code changes: editing `index.js` (JS, no build step), adding PHP classes. No external CLI tools, databases, or services beyond the already-running WordPress environment are required.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHP custom test runner (PHPUnit-style, no Composer autoload) |
| Config file | None — tests are plain PHP files in `tests/` run with `php tests/test-*.php` |
| Quick run command | `php tests/test-rest-controller.php` |
| Full suite command | `for f in tests/test-*.php; do echo "=== $f ==="; php "$f"; done` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EDIT-03 | Products endpoint returns array with asin+title fields | unit (REST controller) | `php tests/test-rest-controller.php` | Yes — existing `test-rest-controller.php` covers `get_products` |
| EDIT-04 | Dropdown selection calls `updateItem('asin', ...)` identically to TextControl | manual (editor UX) | Manual: open block editor, select from dropdown, verify hydration fires | N/A — JS integration test, no build-step test infra |
| PLIB-04 | Admin page renders WP_List_Table with product rows | unit (PHP render) | New: `php tests/test-product-library-admin.php` | No — Wave 0 gap |
| PLIB-04 | Bulk delete removes correct rows by ID | unit (PHP) | New: `php tests/test-product-library-admin.php` (bulk action) | No — Wave 0 gap |
| PLIB-04 | `delete_by_ids()` on product library removes rows | unit (PHP) | New: `php tests/test-product-library-admin.php` (library method) | No — Wave 0 gap |

### Sampling Rate
- **Per task commit:** `for f in tests/test-*.php; do echo "=== $f ==="; php "$f"; done`
- **Per wave merge:** Full suite above
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/test-product-library-admin.php` — covers PLIB-04: WP_List_Table render, bulk delete routing, `delete_by_ids()` correctness
- [ ] `MTB_Affiliate_Product_Library::delete_by_ids()` method — must be added to `class-mtb-affiliate-product-library.php` before the test can assert it

*(Existing `test-rest-controller.php` covers EDIT-03 REST layer. EDIT-04 is manual-only.)*

---

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection of `blocks/affiliate-cards/index.js` — existing imports, hook usage, hydration pathway
- Direct codebase inspection of `includes/class-mtb-affiliate-rest-controller.php` — confirmed `/products` endpoint shape and auth pattern
- Direct codebase inspection of `includes/class-mtb-affiliate-product-library.php` — confirmed `get_recent()`, `get_last()`, absence of `delete_by_ids()`
- Direct codebase inspection of `includes/class-mtb-affiliate-plugin.php` — confirmed `register_settings_page`, `add_options_page` pattern
- Direct codebase inspection of `blocks/affiliate-cards/block.json` — confirmed no new attributes needed for dropdown
- `.planning/STATE.md` — confirmed blocker note on block.json attribute validation warnings (not triggered by this implementation)
- Project memory `feedback_admin_menu_style.md` — confirmed user preference for top-level menu

### Secondary (MEDIUM confidence)
- WordPress Codex WP_List_Table pattern — standard pattern unchanged for 10+ years; verified against core source structure
- WordPress REST API authentication pattern (`X-WP-Nonce` + `wpApiSettings.root`) — confirmed by existing usage in `index.js`

### Tertiary (LOW confidence)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all tools confirmed present in codebase; zero new dependencies
- Architecture: HIGH — dropdown pattern derived directly from existing hydration code; WP_List_Table pattern is WordPress core
- Pitfalls: HIGH — `useEffect` deps array pitfall confirmed by existing code structure; WP_List_Table pitfalls are well-known

**Research date:** 2026-03-25
**Valid until:** 2026-04-24 (stable WordPress APIs, no anticipated breaking changes)
