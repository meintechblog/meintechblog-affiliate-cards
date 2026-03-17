# Inline Affiliate Flow Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** `amazon:ASIN`-Marker im Fließtext und bestehende Amazon-Produktlinks sollen beim Speichern zu verlinktem Inline-Text plus nachgelagerten einzelnen `Affiliate Card`-Blöcken angereichert werden.

**Architecture:** Wir ergänzen den bestehenden Block-/Hydration-Stack um eine konservative Save-time-Absatzverarbeitung. Der Post-Processor wird erweitert, um Paragraph-Blöcke mit Inline-Markern oder Amazon-Produktlinks zu erkennen, den sichtbaren Satztext umzuschreiben, Tracking-Tags sicher aufzulösen und direkt darunter einzelne Affiliate-Blocks zu erzeugen oder zu aktualisieren.

**Tech Stack:** WordPress Gutenberg Block-Markup, PHP, Regex/HTML parsing for block content, Amazon Creators API client, kleine PHP-Tests

---

### Task 1: Erkennungsregeln für Inline-Marker und Produktlinks absichern

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-post-processor.php`

**Step 1: Write the failing test**

Add tests for:
- `amazon:B0CK3L9WD3` inside paragraph text
- existing Amazon `/dp/ASIN` inline links
- multiple inline markers in one paragraph
- duplicate ASINs in the same paragraph

**Step 2: Run test to verify it fails**

Run: `php tests/test-post-processor.php`

Expected: FAIL because the current processor only understands standalone paragraph tokens.

**Step 3: Write minimal implementation**

Extend the processor parsing so it can detect:
- inline `amazon:ASIN` tokens inside paragraph content
- inline Amazon product links with extractable ASINs

Do not transform yet beyond the minimum needed to expose the parsed data.

**Step 4: Run test to verify it passes**

Run: `php tests/test-post-processor.php`

Expected: PASS

**Step 5: Commit**

```bash
git add tests/test-post-processor.php includes/class-mtb-affiliate-post-processor.php
git commit -m "feat: detect inline affiliate references in paragraphs"
```

### Task 2: Inline-Text-Umschreibung für `amazon:ASIN` hinzufügen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-post-processor.php`
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-title-shortener.php`

**Step 1: Write the failing test**

Add a test showing:
- input paragraph contains `amazon:B0CK3L9WD3`
- output paragraph contains a linked text segment `Raspberry Pi 5 (Affiliate-Link)` or, generally, `Amazon-Titel (Affiliate-Link)`
- raw token string no longer appears in the paragraph

**Step 2: Run test to verify it fails**

Run: `php tests/test-post-processor.php`

Expected: FAIL because tokens are not currently rewritten inline.

**Step 3: Write minimal implementation**

Use hydrated Amazon title data to replace each inline marker with a linked text segment:
- visible text: `Titel (Affiliate-Link)`
- href: resolved Amazon detail URL with valid tracking tag

Keep the rest of the paragraph intact.

**Step 4: Run test to verify it passes**

Run: `php tests/test-post-processor.php`

Expected: PASS

**Step 5: Commit**

```bash
git add tests/test-post-processor.php includes/class-mtb-affiliate-post-processor.php
git commit -m "feat: rewrite inline affiliate tokens into linked text"
```

### Task 3: Einzelne Cards direkt unter dem Absatz erzeugen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-post-processor.php`

**Step 1: Write the failing test**

Add a test showing:
- one paragraph with one marker produces one following affiliate block
- one paragraph with two markers produces two following affiliate blocks in order
- duplicate ASINs in one paragraph still produce only one following block

**Step 2: Run test to verify it fails**

Run: `php tests/test-post-processor.php`

Expected: FAIL because blocks are not generated from inline references today.

**Step 3: Write minimal implementation**

Generate one single-product `meintechblog/affiliate-cards` block per unique ASIN directly after the paragraph that triggered it.

**Step 4: Run test to verify it passes**

Run: `php tests/test-post-processor.php`

Expected: PASS

**Step 5: Commit**

```bash
git add tests/test-post-processor.php includes/class-mtb-affiliate-post-processor.php
git commit -m "feat: create affiliate cards from inline paragraph references"
```

### Task 4: Tracking-ID-Validierung konservativ einbauen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/tests/test-amazon-client.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-amazon-client.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-plugin.php`

**Step 1: Write the failing test**

Add tests for:
- derive datestamped partner tag from post date
- prefer validated datestamped tag
- fall back to existing functional tag when desired tag is invalid

**Step 2: Run test to verify it fails**

Run: `php tests/test-amazon-client.php`

Expected: FAIL before the new resolution path exists.

**Step 3: Write minimal implementation**

Implement a conservative resolver that can:
- inspect candidate tags
- validate or reject the datestamped tag
- fall back to existing known-good tag

**Step 4: Run test to verify it passes**

Run: `php tests/test-amazon-client.php`

Expected: PASS

**Step 5: Commit**

```bash
git add tests/test-amazon-client.php includes/class-mtb-affiliate-amazon-client.php includes/class-mtb-affiliate-plugin.php
git commit -m "feat: validate tracking tags for inline affiliate enrichment"
```

### Task 5: Bestehende Cards aktualisieren statt doppeln

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/includes/class-mtb-affiliate-post-processor.php`

**Step 1: Write the failing test**

Add tests showing:
- existing directly-adjacent card with same ASIN is updated, not duplicated
- manual deletion plus remaining inline marker recreates the card on next save

**Step 2: Run test to verify it fails**

Run: `php tests/test-post-processor.php`

Expected: FAIL because adjacency-aware update behavior is not implemented yet.

**Step 3: Write minimal implementation**

Teach the processor to detect directly following affiliate blocks tied to the same paragraph context and update them in place.

**Step 4: Run test to verify it passes**

Run: `php tests/test-post-processor.php`

Expected: PASS

**Step 5: Commit**

```bash
git add tests/test-post-processor.php includes/class-mtb-affiliate-post-processor.php
git commit -m "fix: update adjacent inline affiliate cards without duplication"
```

### Task 6: Live-Test am Referenzbeitrag und Doku

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/README.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/docs/HOWTO-USE.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/docs/EDITOR-WORKFLOW.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/CHANGELOG.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-inline-affiliate-flow/meintechblog-affiliate-cards.php`

**Step 1: Run full verification**

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

Expected: all green, ZIP built successfully.

**Step 2: Verify in WordPress with reference content**

Use the real editor and the real post flow to confirm:
- inline `amazon:ASIN` in a paragraph becomes linked `Titel (Affiliate-Link)`
- one product creates one following card
- multiple inline products create multiple following cards
- `post=21052` is a valid manual/live reference case

**Step 3: Update docs and release metadata**

Document the new inline authoring workflow and mention how it differs from standalone paragraph tokens.

**Step 4: Commit**

```bash
git add README.md docs/HOWTO-USE.md docs/EDITOR-WORKFLOW.md CHANGELOG.md meintechblog-affiliate-cards.php
git commit -m "docs: describe inline affiliate flow"
```
