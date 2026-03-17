# Affiliate Card Editor Hydration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make `amazon:ASIN` create a fully hydrated single-product `Affiliate Card` directly in the Gutenberg editor, including title, image, badge controls, and recoverable error states.

**Architecture:** Keep the single-product `meintechblog/affiliate-cards` block, but add an explicit editor hydration layer backed by a plugin REST endpoint. The editor will replace `amazon:ASIN` with a block immediately, fetch normalized Amazon data through the plugin, persist the returned fields into block attributes, and expose badge, title, benefit, and image-selection controls directly inside the block UI.

**Tech Stack:** WordPress plugin PHP, Gutenberg block editor JS, WordPress REST API, Amazon Creators API, local PHP smoke tests, JS syntax checks.

---

### Task 1: Extend block attributes for hydrated single-card state

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/block.json`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add assertions that the block metadata includes hydrated single-card fields:

- `amazonTitle`
- `detailUrl`
- `images`
- `selectedImageIndex`
- `loadState`
- `loadError`

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`
Expected: FAIL because the new attributes are not declared yet.

**Step 3: Write minimal implementation**

Add the new attributes to `block.json` while keeping `items` for backward compatibility with existing stored blocks.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`
Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/block.json tests/test-block-files.php
git commit -m "feat: add hydrated affiliate card block attributes"
```

### Task 2: Make the Amazon client return image galleries

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-amazon-client.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-amazon-client.php`

**Step 1: Write the failing test**

Extend the Amazon client test so one item is expected to return:

- `images` as an array of URLs
- `image_url` matching the selected default image
- normalized title and detail URL still intact

**Step 2: Run test to verify it fails**

Run: `php tests/test-amazon-client.php`
Expected: FAIL because the client only exposes one primary image today.

**Step 3: Write minimal implementation**

Update the client to request and normalize multiple images from the Creators API response. Keep `image_url` as the default first image for compatibility, but add `images[]`.

**Step 4: Run test to verify it passes**

Run: `php tests/test-amazon-client.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-amazon-client.php tests/test-amazon-client.php
git commit -m "feat: expose amazon image galleries for affiliate cards"
```

### Task 3: Add a REST endpoint for editor hydration

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-plugin.php`
- Create or Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-rest-controller.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-lifecycle.php`

**Step 1: Write the failing test**

Add lifecycle assertions that plugin boot registers REST API wiring for the affiliate card hydration endpoint.

**Step 2: Run test to verify it fails**

Run: `php tests/test-lifecycle.php`
Expected: FAIL because no REST route/controller is registered yet.

**Step 3: Write minimal implementation**

Register a REST route such as `/mtb-affiliate-cards/v1/item` that accepts:

- `asin`
- `postId`

and returns:

- normalized title
- detail URL
- images array
- suggested badge mode
- suggested benefit text when available

**Step 4: Run test to verify it passes**

Run: `php tests/test-lifecycle.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-plugin.php includes/class-mtb-affiliate-rest-controller.php tests/test-lifecycle.php
git commit -m "feat: add affiliate card hydration rest endpoint"
```

### Task 4: Hydrate the block immediately in the editor

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add test assertions that editor code contains:

- REST fetch logic for hydration
- `loadState` handling
- image navigation controls
- badge select UI

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`
Expected: FAIL because the editor only replaces the paragraph and shows a thin static form today.

**Step 3: Write minimal implementation**

Update `index.js` so that:

- `amazon:ASIN` replacement still happens
- new block starts with `loadState: loading`
- a fetch to the plugin REST endpoint hydrates the block
- success stores title, link, images, badge suggestion, and selected image index
- failure stores `loadState: error` and `loadError`

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`
Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js tests/test-block-files.php
git commit -m "feat: hydrate affiliate cards directly in the editor"
```

### Task 5: Build the in-block editing UX

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/editor.css`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add assertions for visible single-card controls in the editor implementation:

- badge dropdown
- `Kurztitel` field
- `Nutzenzeile` field
- left/right image controls
- retry button text such as `Produktdaten neu laden`

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`
Expected: FAIL because the current editor UI lacks hydration/error/image-selection controls.

**Step 3: Write minimal implementation**

Implement the block editing UI so the author can:

- change badge mode in the card itself
- edit title override and benefit
- switch selected image
- retry hydration after failure

Keep old multi-item blocks readable with a warning, but optimize the editing path for single-card blocks only.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`
Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js blocks/affiliate-cards/editor.css tests/test-block-files.php
git commit -m "feat: add single-card editor controls for affiliate blocks"
```

### Task 6: Make server rendering prefer persisted hydrated data

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-renderer.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-core.php`

**Step 1: Write the failing test**

Add a renderer/core test covering:

- persisted selected image wins
- persisted hydrated title/link are used when present
- live fallback still works when persisted data is missing

**Step 2: Run test to verify it fails**

Run: `php tests/test-core.php`
Expected: FAIL because rendering currently only understands one image URL and basic item fields.

**Step 3: Write minimal implementation**

Update render resolution so server-side output uses persisted block attributes first, then falls back to Amazon fetch only when necessary. Ensure the selected image index controls the rendered image.

**Step 4: Run test to verify it passes**

Run: `php tests/test-core.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-block.php includes/class-mtb-affiliate-renderer.php tests/test-core.php
git commit -m "feat: render affiliate cards from persisted hydrated data"
```

### Task 7: Document and ship the new editor workflow

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/README.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/docs/HOWTO-USE.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/docs/EDITOR-WORKFLOW.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/CHANGELOG.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/meintechblog-affiliate-cards.php`

**Step 1: Write the failing test**

Extend `tests/test-block-files.php` or add a small doc assertion so the docs mention:

- direct editor hydration
- badge dropdown
- image switching
- retry path on failures

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`
Expected: FAIL if docs are still outdated.

**Step 3: Write minimal implementation**

Update docs, changelog, and plugin version for the shipped experience.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`
Expected: PASS

**Step 5: Commit**

```bash
git add README.md docs/HOWTO-USE.md docs/EDITOR-WORKFLOW.md CHANGELOG.md meintechblog-affiliate-cards.php
git commit -m "docs: describe hydrated affiliate card editor workflow"
```

### Task 8: Final verification and live WordPress check

**Files:**
- Output: `/Users/hulki/codex/meintechblog-affiliate-cards/build/meintechblog-affiliate-cards.zip`

**Step 1: Run the full local verification suite**

Run:

```bash
php tests/test-core.php
php tests/test-block-files.php
php tests/test-token-scanner.php
php tests/test-settings.php
php tests/test-post-processor.php
php tests/test-amazon-client.php
php tests/test-lifecycle.php
node --check blocks/affiliate-cards/index.js
find . -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/build-zip.sh
```

Expected: all commands pass, build ZIP is created.

**Step 2: Deploy the ZIP to WordPress**

Use the existing WordPress admin flow to upload the new ZIP and replace the installed plugin.

**Step 3: Verify live behavior in the editor**

In a draft post:

1. create a paragraph with `amazon:B0DSKDGZM6`
2. press `Enter`
3. confirm the editor shows a hydrated single card with title, image, badge dropdown, and editable fields
4. switch the badge
5. if multiple images are present, switch image
6. save and reload to confirm persistence

**Step 4: Commit deployment-ready changes**

```bash
git add .
git commit -m "chore: release hydrated affiliate card editor flow"
```
