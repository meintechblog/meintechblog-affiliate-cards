# Plugin Roundout Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Das Plugin zu einem normal installierbaren/deinstallierbaren WordPress-Plugin mit speicherbaren Einstellungen, Post-Save-Autoscan und vorbereiteter Amazon-Creators-API-Anbindung ausbauen.

**Architecture:** Wir behalten den nativen Gutenberg-Block als Frontend-/Editor-Basis und ergänzen darum drei fehlende Schichten: persistente Settings, einen Save-Processor für alleinstehende ASIN-Absatzblöcke und einen PHP-Service für Amazon-Daten. Der Save-Processor mutiert Post-Content kontrolliert und erzeugt bzw. aktualisiert einen dynamischen `meintechblog/affiliate-cards`-Block genau an der Stelle der erkannten Marker.

**Tech Stack:** PHP 8+, WordPress Plugin API, Gutenberg Block API, einfache PHP-Testskripte

---

### Task 1: Settings dauerhaft machen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-settings.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-plugin.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-settings.php`

**Step 1: Write the failing test**

Teste, dass Defaults gemerged werden, gespeicherte Werte gelesen werden und Secrets beim Speichern sanitisiert werden.

**Step 2: Run test to verify it fails**

Run: `php tests/test-settings.php`
Expected: FAIL, weil die Methoden für Persistenz und Sanitizing noch fehlen.

**Step 3: Write minimal implementation**

Ergänze:
- `option_name()`
- `get_all()`
- `save()`
- Sanitizing/Defaults-Merge
- Registrierungs-Helfer für WordPress

**Step 4: Run test to verify it passes**

Run: `php tests/test-settings.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-settings.php includes/class-mtb-affiliate-plugin.php tests/test-settings.php
git commit -m "feat: add persistent affiliate settings"
```

### Task 2: Save-Processor für ASIN-Absätze bauen

**Files:**
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-plugin.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-post-processor.php`

**Step 1: Write the failing test**

Teste:
- alleinstehende Absatz-ASINs werden erkannt
- Marker-Absätze werden entfernt
- ein `meintechblog/affiliate-cards`-Block wird an der ersten Marker-Stelle eingefügt
- vorhandener Affiliate-Block wird aktualisiert statt verdoppelt

**Step 2: Run test to verify it fails**

Run: `php tests/test-post-processor.php`
Expected: FAIL, weil Processor-Klasse und Block-Mutation noch fehlen.

**Step 3: Write minimal implementation**

Ergänze:
- Content-Processing auf String-Basis
- Block-Kommentar-Serialisierung mit Item-Array
- Hook-Einstieg in `save_post`

**Step 4: Run test to verify it passes**

Run: `php tests/test-post-processor.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-post-processor.php includes/class-mtb-affiliate-plugin.php tests/test-post-processor.php
git commit -m "feat: auto-generate affiliate blocks from asin markers"
```

### Task 3: Amazon-Creators-API-Service ergänzen

**Files:**
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-amazon-client.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-plugin.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/includes/class-mtb-affiliate-block.php`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-amazon-client.php`

**Step 1: Write the failing test**

Teste:
- Token-Request-Konfiguration
- Mapping von Amazon-API-Daten auf Item-Struktur
- Tracking-ID-Ableitung aus Post-Datum

**Step 2: Run test to verify it fails**

Run: `php tests/test-amazon-client.php`
Expected: FAIL

**Step 3: Write minimal implementation**

Ergänze:
- HTTP-Request-Builder
- Response-Mapping
- Tracking-ID-Helfer
- injizierbaren Transport für Tests

**Step 4: Run test to verify it passes**

Run: `php tests/test-amazon-client.php`
Expected: PASS

**Step 5: Commit**

```bash
git add includes/class-mtb-affiliate-amazon-client.php includes/class-mtb-affiliate-plugin.php includes/class-mtb-affiliate-block.php tests/test-amazon-client.php
git commit -m "feat: add amazon creators api client"
```

### Task 4: Plugin-Lifecycle und Packaging abrunden

**Files:**
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/uninstall.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/README.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/CHANGELOG.md`
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/scripts/build-zip.sh`
- Test: `/Users/hulki/codex/meintechblog-affiliate-cards/tests/test-lifecycle.php`

**Step 1: Write the failing test**

Teste:
- uninstall löscht Plugin-Optionen
- Build-Skript erzeugt ein sauberes ZIP ohne `.git`

**Step 2: Run test to verify it fails**

Run: `php tests/test-lifecycle.php`
Expected: FAIL

**Step 3: Write minimal implementation**

Ergänze:
- `uninstall.php`
- einfaches ZIP-Build-Skript
- Installations-/Deinstallationsdoku im README

**Step 4: Run test to verify it passes**

Run: `php tests/test-lifecycle.php`
Expected: PASS

**Step 5: Commit**

```bash
git add uninstall.php scripts/build-zip.sh README.md CHANGELOG.md tests/test-lifecycle.php
git commit -m "chore: round out plugin lifecycle and packaging"
```

### Task 5: Vollständige Verifikation

**Files:**
- Verify only

**Step 1: Run focused tests**

```bash
php tests/test-core.php
php tests/test-block-files.php
php tests/test-token-scanner.php
php tests/test-settings.php
php tests/test-post-processor.php
php tests/test-amazon-client.php
php tests/test-lifecycle.php
```

**Step 2: Run syntax checks**

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

**Step 3: Build installable ZIP**

```bash
./scripts/build-zip.sh
```

**Step 4: Inspect clean working tree**

```bash
git status --short
```
