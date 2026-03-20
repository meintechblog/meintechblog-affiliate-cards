# Manual Affiliate Card Hydration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make manually inserted Affiliate Cards auto-hydrate from the ASIN and stop rendering empty product-image placeholders when Amazon data is incomplete.

**Architecture:** Reuse the existing editor hydration endpoint and extend the block editor so manual ASIN edits can trigger the same loading flow as the `amazon:ASIN` paragraph transform. On the server side, tighten render fallbacks so missing image data never produces an empty `<img>` and manual cards can still render a valid affiliate link.

**Tech Stack:** WordPress Gutenberg block editor, PHP block rendering, lightweight PHP regression tests, REST hydration endpoint.

---

### Task 1: Lock the regression down in tests

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-core.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing tests**

- Add a renderer assertion that cards without `image_url` do not emit `img src=""`.
- Add a source-level assertion that the editor block auto-hydrates after manual ASIN entry, not only after paragraph replacement.

**Step 2: Run tests to verify failure**

Run:

```bash
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-core.php
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php
```

### Task 2: Implement the minimal fix

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-renderer.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-rest-controller.php`

**Step 1: Editor**

- Detect valid manual ASIN changes in the block editor.
- Reset stale hydrated data when the ASIN changes.
- Trigger `hydrateAffiliateBlock(...)` automatically for valid ASINs.

**Step 2: Renderer**

- Render the image wrapper only when `image_url` is non-empty.
- Keep title and CTA rendering stable when no image exists.

**Step 3: REST fallback**

- When the Amazon lookup fails, emit a stable affiliate detail URL instead of a naked DP URL without partner tag.

### Task 3: Verify and repair live content

**Files:**
- No repo file changes required for the post repair itself

**Step 1: Run focused verification**

```bash
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-core.php
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-amazon-client.php
find /Users/hulki/codex/meintechblog-affiliate-cards -name '*.php' -print0 | xargs -0 -n1 php -l
node --check /Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js
```

**Step 2: Repair `post 21580`**

- Rehydrate or patch the existing manual card in `post 21580`.
- Verify `content.raw` contains hydrated block attributes.
- Verify `content.rendered` no longer contains `img src=""` and uses an affiliate URL.
