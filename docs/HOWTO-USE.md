# How to use

## Plugin aktivieren

1. Plugin in WordPress installieren
2. Plugin aktivieren
3. Unter `Einstellungen -> Affiliate Card` die Amazon-Credentials eintragen
4. Einstellungen speichern

## Schnelltest im Blog

Der einfachste Test geht so:

1. Öffne einen Testbeitrag oder Entwurf im Block-Editor
2. Erstelle einen neuen Absatz
3. Schreibe in diesen Absatz exakt:

```text
amazon:B0D7955R6N
```

4. Drücke `Enter`
5. Das Plugin soll jetzt:
   - den Absatz sofort im Editor entfernen
   - an dieser Stelle den `Affiliate Card`-Block einfügen
   - kurz danach Titel, Bild und Link laden
   - dir Badge-Dropdown und Bildauswahl direkt im Block zeigen
   - die Karte schon im Editor fast wie die Live-Ausgabe zeigen

## Inline im Fließtext

Für normale Sätze geht jetzt zusätzlich dieser Flow:

1. Öffne einen Beitrag im Block-Editor
2. Schreibe in einen normalen Absatz zum Beispiel:

```text
Ich nutze amazon:B0D7955R6N in diesem Setup.
```

3. Speichere den Beitrag
4. Danach soll das Plugin:
   - `amazon:B0D7955R6N` im Satz zu `Titel (Affiliate-Link)` umschreiben
   - den Affiliate-Link auf das passende Amazon-Produkt mit Tracking-Ziel setzen
   - direkt unter dem Absatz eine `Affiliate Card` erzeugen
5. Wenn du mehrere `amazon:ASIN`-Marker im selben Absatz nutzt, entstehen mehrere einzelne Cards direkt unter diesem Absatz

## Was nicht automatisch erkannt wird

Diese Fälle sollen absichtlich nicht automatisch umgewandelt werden:

- nackte ASIN ohne Prefix
- ASIN mitten in einem Satz
- ASIN in einem längeren Fließtext ohne `amazon:`-Prefix

## Manuelle Nutzung

Du kannst den Block auch manuell einsetzen:

1. Im Editor `Affiliate Card`-Block einfügen
2. Genau ein Produkt pflegen
3. Badge, Nutzenzeile und optionalen Kurztitel setzen
4. Wenn mehrere Bilder da sind, per `Bild zurück` / `Bild weiter` das passende auswählen
5. Beitrag speichern

## Beim Test prüfen

- ist der Token-Absatz direkt nach `Enter` verschwunden?
- wurde an der gleichen Stelle sofort der Affiliate-Block eingefügt?
- sind Bild, Titel und Button direkt im Editor sichtbar?
- wirkt die Karte im Editor bereits fast wie die veröffentlichte Karte?
- lässt sich das Badge per Dropdown umstellen?
- lässt sich bei mehreren Bildern das gewünschte Bild auswählen?
- führen Bild und Button zur richtigen Amazon-Seite?
- passt das Badge zum Beitrag?
  - mit YouTube-Embed: `Im Video verwendet`
  - ohne YouTube-Embed: `Passend zu diesem Setup`
