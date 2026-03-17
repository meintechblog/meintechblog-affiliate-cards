# MeinTechBlog Affiliate Cards

Ein natives WordPress-/Gutenberg-Plugin für `meintechblog.de`, das Amazon-Affiliate-Produkte als kompakte, klickstarke Produktkarten rendert statt als nackte Textlinks.

## Status

Das Repo enthält jetzt eine installierbare Plugin-Basis:

- native Gutenberg-Blockstruktur `meintechblog/affiliate-cards`
- Renderer im Stil der aktuell live getesteten Karten auf `meintechblog.de`
- automatische Titelkürzung im "kurz und knackig"-Stil
- Badge-Logik für `Im Video verwendet` vs. `Passend zu diesem Setup`
- expliziter Editor-Trigger für exakte Absatz-Tokens wie `amazon:B0D7955R6N`
- persistente Plugin-Einstellungen für CTA, Badge, Marketplace und Amazon-Credentials
- direkte Block-Umwandlung im Gutenberg-Editor ohne Speichern/Neuladen
- PHP-Client für die Amazon Creators API
- `uninstall.php` für sauberes Entfernen der Plugin-Optionen
- Build-Skript für ein installierbares ZIP

## Produktziel

Das Plugin soll Beiträge im normalen WordPress-Editor "sexy" machen, ohne HTML-Gefrickel:

- Affiliate-Karten als echter Gutenberg-Block
- Editor-Vorschau statt HTML-Box
- Bild, Titel, Nutzenzeile und CTA im Inhaltsfluss
- explizite Erzeugung des Blocks direkt im Editor
- exakter Trigger über `amazon:ASIN`

## Verhalten

Im Gutenberg-Editor:

1. Du schreibst in einen leeren Absatz genau `amazon:ASIN`
2. Du bestätigst den Absatz, typischerweise mit `Enter`
3. Der Absatz wird direkt im Editor durch einen nativen `Affiliate Card`-Block ersetzt
4. Existiert die ASIN im Beitrag bereits, wird kein doppelter Block erzeugt
5. Produktdaten kommen im Renderpfad aus der Amazon Creators API
6. Titel werden automatisch auf das aktuelle kurze Live-Niveau gekürzt

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
2. Unter `Einstellungen -> Affiliate Card` die Amazon-Credentials eintragen und speichern
3. Einen Beitrag im Block-Editor öffnen
4. In einen leeren Absatz genau `amazon:B0D7955R6N` schreiben
5. `Enter` drücken
6. Das Plugin ersetzt den Absatz direkt im Editor durch einen `Affiliate Card`-Block

### So testest du es am einfachsten

1. Lege einen Testbeitrag oder Entwurf an
2. Schreibe einen normalen Absatz
3. Füge darunter einen neuen Absatz ein, der nur aus `amazon:B0D7955R6N` besteht
4. Drücke `Enter`
5. Prüfe direkt im Editor, ob der Absatz verschwunden ist und stattdessen ein Affiliate-Block erscheint
6. Öffne die Vorschau und klicke auf Bild oder Button
7. Prüfe, ob du auf die passende Amazon-Produktseite mit Tracking-Ziel kommst

### Wichtige Regel

Die automatische Erkennung greift nur, wenn der Absatz exakt `amazon:ASIN` enthält.

- funktioniert: `amazon:B0D7955R6N`
- funktioniert nicht: `B0D7955R6N`
- funktioniert nicht: `Ich nutze amazon:B0D7955R6N im Setup`

### Manuelle Nutzung

Du kannst den `Affiliate Card`-Block auch direkt im Editor einfügen und genau ein Produkt darin pflegen.

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
- REST-Enrichment für frisch erzeugte Blöcke direkt im Editor
