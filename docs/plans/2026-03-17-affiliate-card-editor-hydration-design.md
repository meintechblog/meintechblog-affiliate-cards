# Affiliate Card Editor Hydration Design

## Ziel

Die `Affiliate Card` soll sich im Gutenberg-Editor wie ein vollwertiges Redaktions-Element anfühlen. Nach `amazon:ASIN` plus `Enter` darf kein leerer oder halb kaputter Block entstehen. Stattdessen soll direkt im Editor eine einzelne, benutzbare Produktkarte erscheinen.

## Problem

Der aktuelle Trigger ersetzt einen Absatz sofort durch einen Block, speichert dort aber nur die ASIN. Titel, Bild und Produktlink werden erst spaeter oder nur im Renderpfad aufgeloest. Dadurch wirkt der Block fuer Anwender kaputt:

- im Editor fehlen Titel und Bild
- Fehler sind nicht sichtbar genug
- Badge-Auswahl und Bildwahl sind nicht direkt am Block bedienbar

## Produktentscheidung

Ein Block steht immer fuer genau ein Produkt.

- Trigger bleibt exakt `amazon:ASIN`
- der neue Block wird direkt im Editor erzeugt
- direkt danach hydratisiert sich der Block mit Amazon-Daten
- der Block speichert genug Daten lokal, damit er nicht bei jedem Render neu raten muss

## Editor-UX

### Einfuegen

1. In einen leeren Absatz `amazon:ASIN` schreiben
2. `Enter` druecken
3. Absatz wird sofort durch eine `Affiliate Card` ersetzt
4. Der Block laedt direkt Amazon-Daten fuer genau diese ASIN

### Bearbeiten

Der Block selbst wird zur Arbeitsflaeche:

- Badge-Dropdown oberhalb des Bilds
- Bildbereich mit links/rechts-Navigation, wenn Amazon mehrere Bilder liefert
- Feld fuer `Kurztitel`
- Feld fuer `Nutzenzeile`
- sichtbarer Lade- oder Fehlerstatus statt stiller Leere

## Badge-Logik

Es gibt genau zwei Standardwerte:

- `Im Video verwendet`
- `Passend zu diesem Setup`

Der Default wird automatisch vorgeschlagen:

- mit YouTube-Embed im Beitrag: `Im Video verwendet`
- ohne YouTube-Embed: `Passend zu diesem Setup`

Der Anwender kann das Badge im Block jederzeit per Dropdown aendern.

## Bildlogik

Die Amazon-Anbindung soll nach Moeglichkeit mehrere Bilder je Produkt liefern.

- Bilder werden als Liste im Block gespeichert
- `selectedImageIndex` steuert das aktive Bild
- links/rechts im Block schalten durch die verfuergbaren Bilder
- das ausgewaehlte Bild wird im Block gespeichert und im Frontend verwendet

## Titellogik

- Standard ist der Amazon-Titel
- automatische Verkuerzung bleibt aktiv
- `Kurztitel` kann den angezeigten Titel ueberschreiben
- wenn kein Override gesetzt ist, gewinnt die automatische Verkuerzung

## Datenmodell

Der Block soll mindestens diese Daten tragen:

- `asin`
- `amazonTitle`
- `titleOverride`
- `benefit`
- `badgeMode`
- `detailUrl`
- `images[]`
- `selectedImageIndex`
- `loadState`
- `loadError`

## Robustheit

Wenn Amazon-Daten nicht geladen werden koennen:

- der Block bleibt im Editor sichtbar
- ein klarer Fehlerstatus wird angezeigt
- eine Aktion `Produktdaten neu laden` steht zur Verfuegung

Wenn nur Teilinformationen fehlen:

- bereits bekannte Daten bleiben sichtbar
- nur die fehlenden Teile werden als offen markiert

## Technische Richtung

Die Hydratisierung soll editorseitig ueber einen WordPress-REST-Endpunkt des Plugins laufen. Der Editor-Trigger erzeugt zunaechst einen minimalen Block, der anschliessend per JS genau eine Produktabfrage startet. Der Server liefert normalisierte Produktdaten zurueck, inklusive Bilderliste, Kurztitlempfehlung, Detail-URL und passendem Badge-Vorschlag.

Das Frontend rendert weiterhin serverseitig, nutzt aber bevorzugt die bereits im Block gespeicherten Daten. Nur wenn wichtige Felder fehlen, darf es auf Live-Enrichment zurueckfallen.

## Erfolgskriterien

- `amazon:ASIN` erzeugt direkt im Editor eine brauchbare Karte
- Titel und Bild sind ohne Speichern sichtbar
- Badge ist im Block per Dropdown steuerbar
- mehrere Amazon-Bilder sind im Block auswaehlbar
- leere oder stille Fehlerzustaende treten nicht mehr auf
