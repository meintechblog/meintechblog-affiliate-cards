# Affiliate Card WYSIWYG Editor Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Die `Affiliate Card` soll im Gutenberg-Editor fast wie im Frontend aussehen und dabei weiter direkt hydriert, bearbeitet und gespeichert werden können.

**Architecture:** Wir behalten den bestehenden Hydration- und Render-Flow bei und ersetzen nur die formularlastige Editor-Oberfläche durch eine WYSIWYG-Kartenansicht. Die Block-Attribute, REST-Hydration und das Frontend-Rendering bleiben die Datenbasis; `index.js` und `editor.css` werden zur eigentlichen Umbaufläche.

**Tech Stack:** WordPress Gutenberg Block API, Plain JavaScript, PHP, CSS, WordPress REST API, kleine PHP-/JS-Checks

---

### Task 1: Frontend-Kartenstruktur für den Editor ableiten

**Files:**
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/templates/affiliate-cards.php`
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add assertions that the editor block contains the intended live-card sections:
- badge area
- media area
- body/title area
- CTA preview area

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`

Expected: FAIL because the old form-centric editor markup still exists.

**Step 3: Write minimal implementation**

Refactor the editor render tree in `index.js` so the visible structure is a single card layout rather than stacked form boxes.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`

Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js tests/test-block-files.php
git commit -m "feat: reshape affiliate card editor into live card layout"
```

### Task 2: Move editing controls into the card surface

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/editor.css`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Extend the block file test so it expects:
- inline badge dropdown
- inline title editing surface
- inline benefit editing surface
- image navigation inside the card
- no prominent inline ASIN form row

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`

Expected: FAIL until the controls are relocated.

**Step 3: Write minimal implementation**

Update `index.js` to keep only the author-facing controls inline and move ASIN/reload into inspector-oriented handling. Update `editor.css` so the controls visually merge into the card.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`

Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js blocks/affiliate-cards/editor.css tests/test-block-files.php
git commit -m "feat: embed affiliate card controls into editor preview"
```

### Task 3: Add card-native loading and error states

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/editor.css`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add assertions for:
- card-skeleton loading state
- inline retry/error action within card markup

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`

Expected: FAIL before the new states exist.

**Step 3: Write minimal implementation**

Render loading and error states using the same card shell so the block never falls back to a disconnected utility panel.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`

Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js blocks/affiliate-cards/editor.css tests/test-block-files.php
git commit -m "feat: render affiliate card loading states in card layout"
```

### Task 4: Align editor styling with live card styling

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/editor.css`
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/templates/affiliate-cards.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`

**Step 1: Write the failing test**

Add assertions for the new editor style hooks/classes that mirror the live card sections.

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php`

Expected: FAIL until the CSS hooks are present.

**Step 3: Write minimal implementation**

Bring editor spacing, backgrounds, border treatment, image framing, title hierarchy, button preview, and selected-state emphasis in line with the front-end card.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php`

Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/editor.css tests/test-block-files.php
git commit -m "feat: match affiliate editor card styling to frontend"
```

### Task 5: Verify hydration flow still works in editor

**Files:**
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Inspect: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-rest-controller.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-block-files.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-lifecycle.php`

**Step 1: Write the failing test**

Add or tighten assertions that the `amazon:ASIN` replacement flow still:
- creates one affiliate block
- triggers hydration
- preserves hydrated title and image data paths

**Step 2: Run test to verify it fails**

Run: `php tests/test-block-files.php && php tests/test-lifecycle.php`

Expected: FAIL if the WYSIWYG refactor broke the trigger or hydration path.

**Step 3: Write minimal implementation**

Repair any regressions without changing the agreed user-facing behavior.

**Step 4: Run test to verify it passes**

Run: `php tests/test-block-files.php && php tests/test-lifecycle.php`

Expected: PASS

**Step 5: Commit**

```bash
git add blocks/affiliate-cards/index.js tests/test-block-files.php tests/test-lifecycle.php
git commit -m "fix: preserve affiliate card hydration during editor redesign"
```

### Task 6: Verify end-to-end and refresh docs

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/README.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/docs/HOWTO-USE.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/docs/EDITOR-WORKFLOW.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/CHANGELOG.md`

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

**Step 2: Verify in WordPress editor**

Use the real editor and confirm:
- `amazon:ASIN` + Enter still creates a block
- block immediately looks close to the live card
- badge/title/benefit/image controls are usable in-card

**Step 3: Update docs**

Document that the editor now mirrors the live card and explain where inline controls vs inspector controls live.

**Step 4: Commit**

```bash
git add README.md docs/HOWTO-USE.md docs/EDITOR-WORKFLOW.md CHANGELOG.md build/meintechblog-affiliate-cards.zip
git commit -m "docs: describe wysiwyg affiliate card editor"
```
