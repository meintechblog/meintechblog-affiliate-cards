# Affiliate Audit Admin Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Die bestehende Plugin-Seite `Affiliate Card` um einen Tab `Affiliate Audit` erweitern, der Beiträge mit Affiliate-Potenzial listen, prüfen, geradeziehen und verständlich protokollieren kann.

**Architecture:** Die Lösung bleibt vollständig im bestehenden Plugin. Audit-Daten werden als Post-Meta gespeichert, die Admin-Seite rendert daraus eine WP-nahe Matrix mit Status, Logs und Aktionen. `Prüfen` analysiert Beiträge ohne Inhaltsänderung; `Geradeziehen` nutzt bestehende Affiliate-Logik des Plugins, ergänzt sichere Reparaturen und aktualisiert anschließend Audit-Metadaten.

**Tech Stack:** WordPress Plugin (PHP), bestehende Plugin-Klassen, Post Meta, WP-Admin-UI, vorhandene Amazon-/Post-Processor-Logik, lokale PHP-Tests.

---

### Task 1: Isolierten Arbeitsbereich vorbereiten

**Files:**
- Prüfen: `/Users/hulki/codex/meintechblog-affiliate-cards/.gitignore`
- Create/Use: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/`

**Step 1: Ignorierte Worktree-Basis prüfen**

Run: `git -C /Users/hulki/codex/meintechblog-affiliate-cards check-ignore -q .worktrees && echo ignored`
Expected: `ignored`

**Step 2: Worktree anlegen**

Run: `git -C /Users/hulki/codex/meintechblog-affiliate-cards worktree add /Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin -b codex/affiliate-audit-admin`
Expected: neuer Worktree auf frischem Branch

**Step 3: Baseline-Tests prüfen**

Run in worktree:
`php tests/test-core.php && php tests/test-block-files.php && php tests/test-token-scanner.php && php tests/test-settings.php && php tests/test-post-processor.php && php tests/test-amazon-client.php && php tests/test-lifecycle.php`
Expected: alles `ok`

### Task 2: Audit-Datenmodell test-first absichern

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-settings.php`
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-audit-service.php`
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-audit-service.php`

**Step 1: Failing Test für Audit-Meta-Struktur schreiben**
- Test für Default-Struktur (`status`, `counts`, `log`, `timestamps`)
- Test für human readable Kurzlog aus Audit-Befunden

**Step 2: Test rot laufen lassen**

Run: `php tests/test-audit-service.php`
Expected: FAIL wegen fehlender Klasse/Methoden

**Step 3: Minimale Audit-Service-Klasse schreiben**
- Methoden für Default-Meta
- Methoden für Statusableitung
- Methoden für Kurzlog-Aufbau

**Step 4: Test erneut laufen lassen**

Run: `php tests/test-audit-service.php`
Expected: `ok`

### Task 3: Beitragsscan für Audit-Befunde ergänzen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-post-processor.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-audit-service.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-audit-service.php`

**Step 1: Failing Tests für Audit-Erkennung schreiben**
- erkennt vorhandene Amazon-Links im Fließtext
- zählt vorhandene `Affiliate Card`-Blöcke
- erkennt `amazon:ASIN`-Marker
- erkennt Tracking-ID-Befunde grob (`ok` / `abweichend` / `unklar`)

**Step 2: Rot verifizieren**

Run: `php tests/test-audit-service.php`
Expected: FAIL in neuen Audit-Assertions

**Step 3: Minimalen Scan implementieren**
- `scan_post_content()` im Audit-Service
- Nutzung bestehender Regex-/Processor-Helfer wo sinnvoll

**Step 4: Grün verifizieren**

Run: `php tests/test-audit-service.php`
Expected: `ok`

### Task 4: Settings-Seite auf Tabs umstellen

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-lifecycle.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-plugin.php`

**Step 1: Failing Test für Tab-Rendering/Hook-Vorbereitung schreiben**
- mindestens prüfen, dass Plugin-Klasse Audit-Service initialisiert
- und Audit-Tab-Renderpfad verfügbar macht

**Step 2: Test rot laufen lassen**

Run: `php tests/test-lifecycle.php`
Expected: FAIL

**Step 3: Minimalen Tab-Switch im Settings-Renderer ergänzen**
- Query-Parameter `tab`
- Navigation für `settings` / `audit`
- Render-Branch für Audit-Tab

**Step 4: Test grün laufen lassen**

Run: `php tests/test-lifecycle.php`
Expected: `ok`

### Task 5: Audit-Matrix serverseitig rendern

**Files:**
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-audit-admin-render.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-plugin.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-audit-service.php`

**Step 1: Failing Render-Test schreiben**
- prüft, dass Tabelle Spalten `Datum`, `Beitrag`, `Status`, `Affiliate-Funde`, `Tracking-ID`, `Cards`, `Letzte Prüfung`, `Kurzlog`, `Aktionen` enthält
- prüft Summary-Karten/Filter-Grundstruktur

**Step 2: Test rot laufen lassen**

Run: `php tests/test-audit-admin-render.php`
Expected: FAIL

**Step 3: Minimales Audit-Rendering implementieren**
- Summary-Karten
- Filterformular-Grundstruktur
- List-Table-artige HTML-Tabelle
- lesbare Status-Badges

**Step 4: Test grün laufen lassen**

Run: `php tests/test-audit-admin-render.php`
Expected: `ok`

### Task 6: Audit-Aktionen `Prüfen` und `Geradeziehen` ergänzen

**Files:**
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/tests/test-audit-actions.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-plugin.php`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-audit-service.php`

**Step 1: Failing Tests schreiben**
- `Prüfen` aktualisiert nur Audit-Meta, nicht den Beitrag
- `Geradeziehen` nutzt bestehenden Save-/Processor-Flow und schreibt danach Audit-Meta
- unklare Fälle landen auf `Manuell prüfen`

**Step 2: Rot verifizieren**

Run: `php tests/test-audit-actions.php`
Expected: FAIL

**Step 3: Aktionshandler minimal implementieren**
- nonce-/post-basierte Admin-Aktionen
- Post laden, scannen, optional Inhalt aktualisieren
- Audit-Meta schreiben

**Step 4: Grün verifizieren**

Run: `php tests/test-audit-actions.php`
Expected: `ok`

### Task 7: UX-Polish für Audit-Tab

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/includes/class-mtb-affiliate-plugin.php`
- Create: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/assets/admin.css`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/meintechblog-affiliate-cards.php`

**Step 1: Failing assertion für Admin-Styles/Enqueue ergänzen**
- Test prüft, dass Admin-CSS für Plugin-Seite geladen werden kann

**Step 2: Rot verifizieren**

Run: `php tests/test-lifecycle.php`
Expected: FAIL

**Step 3: Styles ergänzen**
- Summary-Karten
- Status-Badges
- kompakte Aktionsbuttons
- gut lesbare Logs

**Step 4: Grün verifizieren**

Run: `php tests/test-lifecycle.php`
Expected: `ok`

### Task 8: Dokumentation aktualisieren

**Files:**
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/README.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/docs/HOWTO-USE.md`
- Modify: `/Users/hulki/codex/meintechblog-affiliate-cards/.worktrees/codex-affiliate-audit-admin/CHANGELOG.md`

**Step 1: README um Audit-Tab ergänzen**
- was die Matrix zeigt
- wie `Prüfen` / `Geradeziehen` funktionieren

**Step 2: HOWTO ergänzen**
- Altbeiträge über Audit-Seite abarbeiten

**Step 3: Changelog ergänzen**
- neuer Audit-Tab
- Matrix/Logs/Aktionen

**Step 4: Doku kurz gegenlesen**

### Task 9: Vollständige Verifikation

**Files:**
- Prüfen: gesamte Worktree-Änderung

**Step 1: Alle lokalen Tests ausführen**

Run:
`php tests/test-core.php && php tests/test-block-files.php && php tests/test-token-scanner.php && php tests/test-settings.php && php tests/test-post-processor.php && php tests/test-amazon-client.php && php tests/test-lifecycle.php && php tests/test-audit-service.php && php tests/test-audit-admin-render.php && php tests/test-audit-actions.php`
Expected: alles `ok`

**Step 2: Syntaxcheck ausführen**

Run: `find . -name '*.php' -print0 | xargs -0 -n1 php -l`
Expected: keine Syntaxfehler

**Step 3: ZIP bauen**

Run: `./scripts/build-zip.sh`
Expected: `Built .../build/meintechblog-affiliate-cards.zip`

**Step 4: Commit**

```bash
git add .
git commit -m "feat: add affiliate audit admin"
```
