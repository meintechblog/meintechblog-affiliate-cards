# Amazon Token Editor Trigger Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace save-time paragraph token scanning with an explicit Gutenberg editor trigger that converts exact `amazon:ASIN` paragraph input into a native affiliate block on editor commit.

**Architecture:** The existing PHP renderer and block schema stay in place. The behavior change happens in the editor script: we detect exact token paragraphs, resolve or minimally scaffold the product item, replace the paragraph block in-place, and stop using `save_post` as the primary transformation mechanism. Existing PHP save logic is reduced to non-destructive behavior only.

**Tech Stack:** PHP 8+, WordPress block editor data APIs, Gutenberg JavaScript, Amazon Creators API, existing plugin test scripts.

---

### Task 1: Add failing coverage for token-to-block replacement direction

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-post-processor.php`

**Step 1: Write the failing test**

- Update processor tests to reflect the new contract:
  - exact `amazon:ASIN` editor flow is no longer the save-time transform source of truth
  - existing block merge behavior must stay non-destructive

**Step 2: Run test to verify it fails**

Run:
```bash
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php
```

Expected: a test fails until processor behavior is narrowed.

**Step 3: Write minimal implementation**

- Keep processor safe for legacy content, but remove any expectation that it is the main UX path.
- Preserve existing blocks and only handle controlled fallback cases.

**Step 4: Run test to verify it passes**

Run:
```bash
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php
```

Expected: `ok`

**Step 5: Commit**

```bash
git add /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php /Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-post-processor.php
git commit -m "refactor: narrow save-time affiliate token processing"
```

### Task 2: Add editor-side token parsing and block replacement

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/block.json`

**Step 1: Write the failing test**

Document a manual editor acceptance case:
- create paragraph block with `amazon:B0CK3L9WD3`
- press `Enter`
- paragraph is replaced immediately with one affiliate block

**Step 2: Run test to verify it fails**

Expected: current editor leaves the paragraph untouched until save.

**Step 3: Write minimal implementation**

- register editor-side detection for exact `amazon:ASIN` paragraph content
- trigger on block commit / `Enter`
- replace the matching paragraph block with `meintechblog/affiliate-cards`
- create one product item per token block

**Step 4: Run test to verify it passes**

Expected: editor replaces the paragraph immediately without saving.

**Step 5: Commit**

```bash
git add /Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js /Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/block.json
git commit -m "feat: convert amazon tokens into affiliate blocks in editor"
```

### Task 3: Add duplicate detection and editor notice

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`

**Step 1: Write the failing test**

Manual acceptance case:
- same ASIN already exists in another affiliate block
- user enters the same `amazon:ASIN` token again
- no duplicate block is created
- editor shows a notice

**Step 2: Run test to verify it fails**

Expected: current editor would create a duplicate or do nothing silently.

**Step 3: Write minimal implementation**

- inspect existing affiliate block items in editor state
- if ASIN already exists:
  - remove token paragraph
  - emit notice
  - skip new block creation

**Step 4: Run test to verify it passes**

Expected: duplicate token cannot silently create another block.

**Step 5: Commit**

```bash
git add /Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js
git commit -m "feat: prevent duplicate affiliate blocks in editor"
```

### Task 4: Add item enrichment path for newly created blocks

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-amazon-client.php`

**Step 1: Write the failing test**

Manual acceptance case:
- token conversion creates a block
- block already shows useful title/image after insertion, or at least on immediate reload

**Step 2: Run test to verify it fails**

Expected: created block is too bare or relies entirely on later rendering.

**Step 3: Write minimal implementation**

- prefer existing block fallback resolution path
- if editor can only create `{ asin }`, ensure server render enriches immediately
- if practical, add an internal REST-backed enrichment request later; keep version 1 minimal

**Step 4: Run test to verify it passes**

Expected: created block becomes useful without awkward manual repair.

**Step 5: Commit**

```bash
git add /Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php /Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js /Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-amazon-client.php
git commit -m "feat: enrich editor-created affiliate blocks"
```

### Task 5: Deploy and verify on live WordPress

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/CHANGELOG.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/meintechblog-affiliate-cards.php`
- Output: `/Users/hulki/codex/meintechblog-affiliate-cards/build/meintechblog-affiliate-cards.zip`

**Step 1: Write the failing test**

Manual live acceptance:
- in `post 21368`, enter `amazon:B0CK3L9WD3` in an empty paragraph
- press `Enter`
- paragraph becomes an affiliate block immediately

**Step 2: Run test to verify it fails**

Expected: current live plugin still requires save-time behavior.

**Step 3: Write minimal implementation**

- bump plugin version
- build zip
- upload update to WordPress

**Step 4: Run test to verify it passes**

Run:
```bash
php /Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php
curl -s -H "Authorization: Basic ..." "https://meintechblog.de/wp-json/wp/v2/plugins?search=meintechblog-affiliate-cards&context=edit"
```

Expected:
- tests pass
- live plugin version updated
- editor interaction behaves as designed

**Step 5: Commit**

```bash
git add /Users/hulki/codex/meintechblog-affiliate-cards
git commit -m "feat: add editor-side amazon token conversion"
```
