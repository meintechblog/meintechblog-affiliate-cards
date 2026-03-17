# MeinTechBlog Affiliate Cards

Ein natives WordPress-/Gutenberg-Plugin für `meintechblog.de`, das Amazon-Affiliate-Produkte als kompakte, klickstarke Produktkarten rendert statt als nackte Textlinks.

## Status

Das Repo enthält bereits den aktuellen Prototyp-Kern:

- native Gutenberg-Blockstruktur `meintechblog/affiliate-cards`
- Renderer im Stil der aktuell live getesteten Karten auf `meintechblog.de`
- automatische Titelkürzung im "kurz und knackig"-Stil
- Badge-Logik für `Im Video verwendet` vs. `Passend zu diesem Setup`
- Scanner für eigenständige ASIN-Textblöcke wie `B0D7955R6N` oder `amazon:B0D7955R6N`

## Produktziel

Das Plugin soll Beiträge im normalen WordPress-Editor "sexy" machen, ohne HTML-Gefrickel:

- Affiliate-Karten als echter Gutenberg-Block
- Editor-Vorschau statt HTML-Box
- Bild, Titel, Nutzenzeile und CTA im Inhaltsfluss
- automatische Erzeugung/Aktualisierung des Blocks beim Speichern
- Erkennung von allein stehenden ASIN-Blöcken im Editor

## Geplantes Verhalten

Beim Speichern eines Beitrags:

1. Das Plugin scannt eigenständige Textblöcke mit `ASIN` oder `amazon:ASIN`
2. Diese Textblöcke werden entfernt
3. Ein `Affiliate Cards`-Block wird an genau dieser Stelle erzeugt oder aktualisiert
4. Produktdaten kommen aus der Amazon Creators API
5. Titel werden automatisch auf das aktuelle kurze Live-Niveau gekürzt

## Repo-Struktur

- `meintechblog-affiliate-cards.php`
  Plugin-Bootstrap
- `includes/`
  Kernlogik für Shortener, Badge, Renderer, Scanner und Blockregistrierung
- `blocks/affiliate-cards/`
  Gutenberg-Blockdateien
- `templates/`
  dynamische Server-Render-Ausgabe
- `assets/`
  Frontend-CSS
- `tests/`
  kleine lokale PHP-Checks für Kernlogik und Block-Skeleton
- `docs/`
  Design, Plan und Statusnotizen

## Lokale Verifikation

```bash
php tests/test-core.php
php tests/test-block-files.php
php tests/test-token-scanner.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Nächste Schritte

- echte WordPress-Settings mit Persistenz
- PHP-Creators-API-Service
- Save-Hooks für Autoscan und Block-Aktualisierung
- Migration alter Amazon-Textlinklisten
- Packaging und Installation auf dem Live-Blog
