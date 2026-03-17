# MeinTechBlog Affiliate Cards

Ein natives WordPress-/Gutenberg-Plugin für `meintechblog.de`, das Amazon-Affiliate-Produkte als kompakte, klickstarke Produktkarten rendert statt als nackte Textlinks.

## Status

Das Repo enthält jetzt eine installierbare Plugin-Basis:

- native Gutenberg-Blockstruktur `meintechblog/affiliate-cards`
- Renderer im Stil der aktuell live getesteten Karten auf `meintechblog.de`
- automatische Titelkürzung im "kurz und knackig"-Stil
- Badge-Logik für `Im Video verwendet` vs. `Passend zu diesem Setup`
- Scanner für eigenständige ASIN-Textblöcke wie `B0D7955R6N` oder `amazon:B0D7955R6N`
- persistente Plugin-Einstellungen für CTA, Badge, Marketplace und Amazon-Credentials
- `save_post`-Hook, der eigenständige ASIN-Absätze in einen nativen Affiliate-Block umwandelt
- PHP-Client für die Amazon Creators API
- `uninstall.php` für sauberes Entfernen der Plugin-Optionen
- Build-Skript für ein installierbares ZIP

## Produktziel

Das Plugin soll Beiträge im normalen WordPress-Editor "sexy" machen, ohne HTML-Gefrickel:

- Affiliate-Karten als echter Gutenberg-Block
- Editor-Vorschau statt HTML-Box
- Bild, Titel, Nutzenzeile und CTA im Inhaltsfluss
- automatische Erzeugung/Aktualisierung des Blocks beim Speichern
- Erkennung von allein stehenden ASIN-Blöcken im Editor

## Verhalten

Beim Speichern eines Beitrags:

1. Das Plugin scannt eigenständige Textblöcke mit `ASIN` oder `amazon:ASIN`
2. Diese Textblöcke werden entfernt
3. Ein `Affiliate Cards`-Block wird an genau dieser Stelle erzeugt oder aktualisiert
4. Produktdaten kommen im Renderpfad aus der Amazon Creators API
5. Titel werden automatisch auf das aktuelle kurze Live-Niveau gekürzt

## Installation

### Als ZIP

```bash
./scripts/build-zip.sh
```

Danach liegt das Paket unter:

```bash
build/meintechblog-affiliate-cards.zip
```

Dieses ZIP kannst du normal über `Plugins -> Installieren -> Plugin hochladen` in WordPress einspielen.

### Direkt im Plugin-Ordner

Alternativ kann der komplette Repo-Inhalt unter `wp-content/plugins/meintechblog-affiliate-cards/` liegen. WordPress erkennt das Plugin über [meintechblog-affiliate-cards.php](/Users/hulki/codex/meintechblog-affiliate-cards/meintechblog-affiliate-cards.php).

## Deinstallation

- Deaktivieren wie jedes normale Plugin über das WordPress-UI
- Löschen über das WordPress-UI entfernt die Plugin-Dateien
- [uninstall.php](/Users/hulki/codex/meintechblog-affiliate-cards/uninstall.php) entfernt zusätzlich die Plugin-Option `mtb_affiliate_cards_settings`

## Repo-Struktur

- `meintechblog-affiliate-cards.php`
  Plugin-Bootstrap
- `includes/`
  Kernlogik für Shortener, Badge, Renderer, Scanner, Amazon-Client, Post-Processor und Blockregistrierung
- `blocks/affiliate-cards/`
  Gutenberg-Blockdateien
- `templates/`
  dynamische Server-Render-Ausgabe
- `assets/`
  Frontend-CSS
- `tests/`
  kleine lokale PHP-Checks für Kernlogik, Lifecycle und Autoscan-Verhalten
- `scripts/`
  Build-Skript für das Plugin-ZIP
- `docs/`
  Design, Plan und Statusnotizen

## Lokale Verifikation

```bash
php tests/test-core.php
php tests/test-block-files.php
php tests/test-token-scanner.php
php tests/test-settings.php
php tests/test-post-processor.php
php tests/test-amazon-client.php
php tests/test-lifecycle.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
./scripts/build-zip.sh
```

## Nächste Schritte

- Migration alter Amazon-Textlinklisten
- Editor-Vorschau näher an das Frontend ziehen
- Installation und Live-Test auf `meintechblog.de`
