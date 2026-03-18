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
- WYSIWYG-Editoransicht nah an der echten Live-Karte
- Inline-Fließtext-Workflow per `amazon:ASIN` mit automatischer Satz-Umschreibung und Card-Erzeugung beim Speichern
- neuer Audit-Tab in den Plugin-Einstellungen mit Post-Matrix, Status, Logs und Aktionen `Prüfen` / `Geradeziehen`
- PHP-Client für die Amazon Creators API
- `uninstall.php` für sauberes Entfernen der Plugin-Optionen
- Build-Skript für ein installierbares ZIP

## Produktziel

Das Plugin soll Beiträge im normalen WordPress-Editor "sexy" machen, ohne HTML-Gefrickel:

- Affiliate-Karten als echter Gutenberg-Block
- Editor-Vorschau nahe an der echten Live-Karte
- Bild, Titel, Nutzenzeile und CTA im Inhaltsfluss
- explizite Erzeugung des Blocks direkt im Editor
- exakter Trigger über `amazon:ASIN`
- zusätzlicher Save-Flow für Inline-Affiliate-Stellen mitten im Absatz

## Verhalten

Im Gutenberg-Editor:

1. Du schreibst in einen leeren Absatz genau `amazon:ASIN`
2. Du bestätigst den Absatz, typischerweise mit `Enter`
3. Der Absatz wird direkt im Editor durch einen nativen `Affiliate Card`-Block ersetzt
4. Der Block lädt direkt Titel, Bild, Link und Badge-Vorschlag über die Plugin-REST-Anbindung
5. Existiert die ASIN im Beitrag bereits, wird kein doppelter Block erzeugt
6. Im Block kannst du Badge, Kurztitel, Nutzenzeile und Bildauswahl direkt bearbeiten
7. Die Kartenansicht im Editor sieht dabei bereits fast wie die Live-Ausgabe aus

Beim Speichern eines Beitrags:

1. Du schreibst im Fließtext z. B. `Ich nutze amazon:B0CK3L9WD3 sehr gern.`
2. Beim Speichern wird der Marker zu `Raspberry Pi 5 (Affiliate-Link)` mit Amazon-Ziel umgeschrieben
3. Direkt unter diesem Absatz entsteht automatisch eine einzelne `Affiliate Card`
4. Bei mehreren `amazon:ASIN`-Markern im selben Absatz entstehen mehrere Cards in derselben Reihenfolge

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
6. Das Plugin ersetzt den Absatz direkt im Editor durch einen `Affiliate Card`-Block und lädt sofort die Produktdaten

### Inline im Fließtext

1. Öffne einen Beitrag im Block-Editor
2. Schreibe in einen normalen Absatz z. B. `Ich nutze amazon:B0D7955R6N im Setup`
3. Speichere den Beitrag
4. Prüfe danach:
   - der Marker im Satz wurde zu `Titel (Affiliate-Link)`
   - direkt unter dem Absatz wurde eine `Affiliate Card` eingefügt
   - bei mehreren Markern im selben Absatz wurden mehrere Cards erzeugt

### Affiliate Audit

Unter `Einstellungen -> Affiliate Card -> Affiliate Audit` gibt es jetzt eine Redaktionsansicht fuer bestehende Beitraege:

1. Die Matrix listet die neuesten Blogposts mit Status, Affiliate-Funden, Tracking-Befund, Card-Anzahl und Kurzlog
2. `Prüfen` scannt einen Beitrag ohne Inhaltsaenderung und schreibt einen lesbaren Audit-Status
3. `Geradeziehen` fuehrt sichere Reparaturen aus, richtet bestehende Amazon-Produktlinks und Affiliate-Cards auf den Datums-Tag des Beitrags aus und aktualisiert den Audit-Log
4. `Öffnen` springt direkt in den WordPress-Editor des Beitrags

Damit koennen wir nach und nach alte Beitraege sauber pruefen und ueberarbeiten, ohne blind Bulk-Aenderungen zu machen.

### So testest du es am einfachsten

1. Lege einen Testbeitrag oder Entwurf an
2. Schreibe einen normalen Absatz
3. Füge darunter einen neuen Absatz ein, der nur aus `amazon:B0D7955R6N` besteht
4. Drücke `Enter`
5. Prüfe direkt im Editor, ob Titel und Bild ohne Reload erscheinen
6. Ändere testweise das Badge oder wechsle ein Bild weiter
7. Öffne die Vorschau und klicke auf Bild oder Button
8. Prüfe, ob du auf die passende Amazon-Produktseite mit Tracking-Ziel kommst

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
  Frontend- und Admin-CSS
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
- leichte API-/Editor-Caches gegen unnötige Amazon-Requests
- Bulk-Aufwertung alter Inline-Affiliate-Links
