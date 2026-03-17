# Affiliate Plugin Editor UX Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the first real `meintechblog-affiliate-cards` WordPress plugin with a native Gutenberg block, dynamic frontend rendering, and short-title logic matching the current live affiliate-card prototype.

**Architecture:** The plugin will live as a standalone WordPress plugin folder in this repo and use a dynamic block. PHP handles Amazon API access, caching, title shortening, badge logic, and frontend render output. JavaScript provides Gutenberg controls and an editor preview backed by the same card schema used on the server.

**Tech Stack:** PHP 8+, WordPress block APIs, Gutenberg block editor JavaScript, Amazon Creators API, `unittest`/Python prototype for reference, WordPress transients/options.

---

### Task 1: Create the plugin scaffold and bootstrap

**Files:**
- Create: `wordpress-plugin/meintechblog-affiliate-cards/meintechblog-affiliate-cards.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-plugin.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-settings.php`

**Step 1: Write the failing test**

Document a manual acceptance check:
- plugin loads in WordPress
- admin menu entry appears

**Step 2: Run test to verify it fails**

Run after installation in WordPress admin.
Expected: plugin not yet present.

**Step 3: Write minimal implementation**

- plugin header file
- bootstrap singleton
- register admin settings page hook

**Step 4: Run test to verify it passes**

Expected: plugin activates and settings page appears.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: scaffold affiliate cards plugin"
```

### Task 2: Add Creators API settings and caching service

**Files:**
- Modify: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-settings.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-api.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-cache.php`

**Step 1: Write the failing test**

Add a small PHP or manual service check spec for:
- reading stored credentials
- returning cached token if present

**Step 2: Run test to verify it fails**

Expected: service classes do not exist yet.

**Step 3: Write minimal implementation**

- store credential options
- token request to `https://api.amazon.co.uk/auth/o2/token`
- transient caching for token and ASIN payloads

**Step 4: Run test to verify it passes**

Expected: credentials save and cached token flow works.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: add creators api settings and cache service"
```

### Task 3: Add title-shortening and badge logic services

**Files:**
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-title-shortener.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-badge-resolver.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/tests/test-title-shortener.md`

**Step 1: Write the failing test**

Define example cases:
- long USB-C tester title shortens to `USB-C Tester Messgerät`
- long PoE splitter title shortens to `Waveshare Industrial Gigabit PoE Splitter`
- YouTube embed in post content resolves to `Im Video verwendet`

**Step 2: Run test to verify it fails**

Expected: logic not implemented yet.

**Step 3: Write minimal implementation**

- regex cleanup rules
- ~55-character target logic
- optional manual override support
- YouTube-aware badge resolver

**Step 4: Run test to verify it passes**

Expected: sample titles and badge labels match the current live behavior.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: add title shortener and badge resolver"
```

### Task 4: Register the dynamic Gutenberg block

**Files:**
- Create: `wordpress-plugin/meintechblog-affiliate-cards/blocks/affiliate-cards/block.json`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/templates/affiliate-cards.php`

**Step 1: Write the failing test**

Manual block acceptance:
- block appears in inserter
- block renders placeholder preview in editor

**Step 2: Run test to verify it fails**

Expected: block not registered yet.

**Step 3: Write minimal implementation**

- register block from metadata
- add render callback
- accept product array attributes plus block-level settings

**Step 4: Run test to verify it passes**

Expected: block appears and renders on frontend.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: register affiliate cards block"
```

### Task 5: Build the editor UI for repeatable products

**Files:**
- Create: `wordpress-plugin/meintechblog-affiliate-cards/blocks/affiliate-cards/index.js`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/blocks/affiliate-cards/edit.js`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/blocks/affiliate-cards/editor.css`

**Step 1: Write the failing test**

Manual editor UX checks:
- add product row
- edit ASIN
- edit benefit line
- add optional short title override
- reorder/remove rows

**Step 2: Run test to verify it fails**

Expected: no editor controls yet.

**Step 3: Write minimal implementation**

- `Affiliate Cards` block editor component
- repeatable product controls in inspector and/or block body
- preview cards inside editor using current attributes

**Step 4: Run test to verify it passes**

Expected: editor workflow feels native and usable without touching HTML.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: add affiliate cards editor ui"
```

### Task 6: Port the current frontend card style into the plugin renderer

**Files:**
- Modify: `wordpress-plugin/meintechblog-affiliate-cards/templates/affiliate-cards.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/assets/frontend.css`

**Step 1: Write the failing test**

Manual visual comparison:
- rendered plugin cards should closely match the current live `post 21368` design

**Step 2: Run test to verify it fails**

Expected: block output looks generic or incomplete.

**Step 3: Write minimal implementation**

- move the live prototype card markup/CSS into plugin template/assets
- keep image/title/benefit/CTA structure
- support dark mode selectors

**Step 4: Run test to verify it passes**

Expected: frontend closely matches the current live card look.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: add frontend affiliate card styling"
```

### Task 7: Add a migration helper for old text-link lists

**Files:**
- Create: `wordpress-plugin/meintechblog-affiliate-cards/includes/class-mtb-affiliate-migrator.php`
- Create: `wordpress-plugin/meintechblog-affiliate-cards/tests/test-migrator.md`

**Step 1: Write the failing test**

Manual acceptance:
- old Amazon link lists can be detected and converted into block data

**Step 2: Run test to verify it fails**

Expected: no migration helper exists.

**Step 3: Write minimal implementation**

- parse `amazon.de/dp/<ASIN>` links from post content
- build product-array payload for the block
- preserve optional source labels where useful

**Step 4: Run test to verify it passes**

Expected: old posts can be upgraded without manual HTML editing.

**Step 5: Commit**

```bash
git add wordpress-plugin/meintechblog-affiliate-cards
git commit -m "feat: add affiliate link migration helper"
```
