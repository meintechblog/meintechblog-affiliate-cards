# How to use

## Plugin aktivieren

1. Plugin in WordPress installieren
2. Plugin aktivieren
3. Unter `Einstellungen -> Affiliate Cards` die Amazon-Credentials eintragen
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
   - an dieser Stelle den `Affiliate Cards`-Block einfügen

## Was nicht automatisch erkannt wird

Diese Fälle sollen absichtlich nicht automatisch umgewandelt werden:

- nackte ASIN ohne Prefix
- ASIN mitten in einem Satz
- ASIN in einem längeren Fließtext
- `amazon:ASIN` zusammen mit weiterem Text im gleichen Absatz

## Manuelle Nutzung

Du kannst den Block auch manuell einsetzen:

1. Im Editor `Affiliate Cards`-Block einfügen
2. Produkte pflegen
3. Nutzenzeilen und optionale Kurztitel setzen
4. Beitrag speichern

## Beim Test prüfen

- ist der Token-Absatz direkt nach `Enter` verschwunden?
- wurde an der gleichen Stelle sofort der Affiliate-Block eingefügt?
- sind Bild, Titel und Button sichtbar?
- führen Bild und Button zur richtigen Amazon-Seite?
- passt das Badge zum Beitrag?
  - mit YouTube-Embed: `Im Video verwendet`
  - ohne YouTube-Embed: `Passend zu diesem Setup`
