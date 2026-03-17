# Editor Workflow

## Ziel

Der Editor soll sich normal anfühlen. Keine HTML-Snippets, keine Shortcode-Hölle.
Die `Affiliate Card` soll sich dabei im Editor fast wie die veröffentlichte Karte anfühlen.

## Gewünschter Flow

### Manuell

1. `Affiliate Card`-Block einfügen
2. Ein Produkt als ASIN pflegen
3. Nutzenzeile und optionalen Kurztitel setzen
4. Vorschau direkt im Editor sehen
5. Titel, Nutzenzeile, Badge und Bild direkt in der Kartenoptik anpassen

### Automatisch

1. Im Beitrag einen eigenen Absatz nur mit `amazon:B0D7955R6N` schreiben
2. `Enter` drücken oder den Block committen
3. Plugin entfernt diesen Token-Block sofort im Editor
4. Plugin setzt an genau dieser Stelle einen nativen `Affiliate Card`-Block
5. Der Block hydriert sich sofort mit Titel, Bild, Link und Badge-Vorschlag
6. Badge, Bildwahl und Kurztexte sind direkt im Block bearbeitbar

## WYSIWYG-Prinzip

- Die Kartenansicht im Editor lehnt sich bewusst an die Live-Ausgabe an
- Badge, Bild, Titel, Nutzenzeile und CTA bleiben an ihrer echten visuellen Position
- Technische Einstellungen wie ASIN und manuelles Reload liegen im Inspector
- Lade- und Fehlerzustände bleiben in der Kartenhülle sichtbar statt als lose Formularmeldungen

## Regeln für Auto-Erkennung

- nur eigenständige Absatzblöcke mit exakt `amazon:ASIN`
- keine nackten ASINs ohne Prefix
- nicht mitten im Satz
- nicht in beliebigem Fließtext

## Kontrollprinzip

- Der Block muss ganz normal löschbar sein
- Ein neuer Block entsteht nur durch einen neuen `amazon:ASIN`-Token
- Wenn die ASIN bereits im Beitrag existiert, wird kein Duplikat erzeugt
