# Editor Workflow

## Ziel

Der Editor soll sich normal anfühlen. Keine HTML-Snippets, keine Shortcode-Hölle.

## Gewünschter Flow

### Manuell

1. `Affiliate Cards`-Block einfügen
2. Produkte als ASINs pflegen
3. Nutzenzeile und optionalen Kurztitel setzen
4. Vorschau direkt im Editor sehen

### Automatisch

1. Im Beitrag einen eigenen Absatz nur mit `B0D7955R6N` oder `amazon:B0D7955R6N` schreiben
2. Beitrag speichern
3. Plugin entfernt diesen Marker-Block
4. Plugin setzt oder aktualisiert den `Affiliate Cards`-Block an genau dieser Stelle

## Regeln für Auto-Erkennung

- nur eigenständige Text-/Absatzblöcke
- nicht mitten im Satz
- nicht in beliebigem Fließtext

## Kontrollprinzip

- Der Block muss ganz normal löschbar sein
- Wenn keine ASIN-Marker mehr im Beitrag stehen, bleibt er auch gelöscht
- Wenn Marker noch da sind, darf er beim nächsten Speichern wieder entstehen
