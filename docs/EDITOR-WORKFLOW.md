# Editor Workflow

## Ziel

Der Editor soll sich normal anfühlen. Keine HTML-Snippets, keine Shortcode-Hölle.

## Gewünschter Flow

### Manuell

1. `Affiliate Card`-Block einfügen
2. Ein Produkt als ASIN pflegen
3. Nutzenzeile und optionalen Kurztitel setzen
4. Vorschau direkt im Editor sehen

### Automatisch

1. Im Beitrag einen eigenen Absatz nur mit `amazon:B0D7955R6N` schreiben
2. `Enter` drücken oder den Block committen
3. Plugin entfernt diesen Token-Block sofort im Editor
4. Plugin setzt an genau dieser Stelle einen nativen `Affiliate Card`-Block

## Regeln für Auto-Erkennung

- nur eigenständige Absatzblöcke mit exakt `amazon:ASIN`
- keine nackten ASINs ohne Prefix
- nicht mitten im Satz
- nicht in beliebigem Fließtext

## Kontrollprinzip

- Der Block muss ganz normal löschbar sein
- Ein neuer Block entsteht nur durch einen neuen `amazon:ASIN`-Token
- Wenn die ASIN bereits im Beitrag existiert, wird kein Duplikat erzeugt
