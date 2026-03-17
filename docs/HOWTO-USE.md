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
3. Schreibe in diesen Absatz nur eine ASIN, zum Beispiel:

```text
B0D7955R6N
```

4. Speichere den Beitrag
5. Das Plugin soll jetzt:
   - den ASIN-Absatz entfernen
   - an dieser Stelle den `Affiliate Cards`-Block einfügen

## Alternative Schreibweise

Statt der nackten ASIN geht auch:

```text
amazon:B0D7955R6N
```

## Was nicht automatisch erkannt wird

Diese Fälle sollen absichtlich nicht automatisch umgewandelt werden:

- ASIN mitten in einem Satz
- ASIN in einem längeren Fließtext
- ASIN zusammen mit weiterem Text im gleichen Absatz

## Manuelle Nutzung

Du kannst den Block auch manuell einsetzen:

1. Im Editor `Affiliate Cards`-Block einfügen
2. Produkte pflegen
3. Nutzenzeilen und optionale Kurztitel setzen
4. Beitrag speichern

## Beim Test prüfen

- ist der Marker-Absatz verschwunden?
- wurde an der gleichen Stelle der Affiliate-Block eingefügt?
- sind Bild, Titel und Button sichtbar?
- führen Bild und Button zur richtigen Amazon-Seite?
- passt das Badge zum Beitrag?
  - mit YouTube-Embed: `Im Video verwendet`
  - ohne YouTube-Embed: `Passend zu diesem Setup`
