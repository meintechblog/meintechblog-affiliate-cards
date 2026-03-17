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

## How to use

Die kurze Version:

1. Plugin in WordPress aktivieren
2. Unter `Einstellungen -> Affiliate Cards` die Amazon-Credentials eintragen und speichern
3. Einen Beitrag im Block-Editor öffnen
4. In einen eigenen Absatz nur eine ASIN schreiben, zum Beispiel `B0D7955R6N`
5. Beitrag speichern
6. Das Plugin entfernt diesen Marker-Absatz und setzt an genau dieser Stelle automatisch den `Affiliate Cards`-Block ein

### So testest du es am einfachsten

1. Lege einen Testbeitrag oder Entwurf an
2. Schreibe einen normalen Absatz
3. Füge darunter einen neuen Absatz ein, der nur aus `B0D7955R6N` besteht
4. Speichere den Beitrag
5. Prüfe, ob der ASIN-Absatz verschwunden ist und stattdessen eine Affiliate-Karte erscheint
6. Öffne die Vorschau und klicke auf Bild oder Button
7. Prüfe, ob du auf die passende Amazon-Produktseite mit Tracking-Ziel kommst

### Wichtige Regel

Die automatische Erkennung greift nur, wenn die ASIN allein in einem eigenen Absatz steht.

- funktioniert: `B0D7955R6N`
- funktioniert auch: `amazon:B0D7955R6N`
- funktioniert nicht: `Ich nutze B0D7955R6N im Setup`

### Manuelle Nutzung

Du kannst den `Affiliate Cards`-Block auch direkt im Editor einfügen und Produkte dort pflegen.

Mehr dazu steht in [HOWTO-USE.md](/Users/hulki/codex/meintechblog-affiliate-cards/docs/HOWTO-USE.md) und [EDITOR-WORKFLOW.md](/Users/hulki/codex/meintechblog-affiliate-cards/docs/EDITOR-WORKFLOW.md).

## Deinstallation

- Deaktivieren wie jedes normale Plugin über das WordPress-UI
- Löschen über das WordPress-UI entfernt die Plugin-Dateien
- [uninstall.php](/Users/hulki/codex/meintechblog-affiliate-cards/uninstall.php) entfernt zusätzlich die Plugin-Option `mtb_affiliate_cards_settings`

## Lizenz

Dieses Repo nutzt die `Energy Community License (ECL-1.0)` in [LICENSE.md](/Users/hulki/codex/meintechblog-affiliate-cards/LICENSE.md), inhaltlich angelehnt an die Lizenz aus [chloepriceless/dvhub](https://github.com/chloepriceless/dvhub) mit auf dieses Projekt angepasster Copyright-Zeile.

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
