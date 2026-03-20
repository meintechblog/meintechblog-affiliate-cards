# Manual Affiliate Card Hydration Design

## Goal

Manuell eingefuegte `Affiliate Card`-Bloecke sollen sich fuer Redakteure genauso verhalten wie per `amazon:ASIN` erzeugte Karten: ASIN eintragen, danach automatisch Titel, Bild und Affiliate-Link laden, ohne leere Bildflaechen oder nackte Amazon-Fallback-Links.

## Current Problem

- Der Editor startet die Hydration aktuell nur beim `amazon:ASIN`-Paragraph-Trigger.
- Wird ein Block manuell eingefuegt und nur die ASIN gesetzt, bleiben `amazonTitle`, `images`, `detailUrl` und `loadState` leer.
- Wenn der Amazon-Fetch fuer einen Datums-Partner-Tag keinen Treffer liefert, rendert der Block mit leerem `img src=""` und nacktem `https://www.amazon.de/dp/...`.

## Design

### 1. Editor-Hydration bei manueller ASIN-Eingabe

- Sobald ein `Affiliate Card`-Block eine gueltige 10-stellige ASIN enthaelt und noch nicht hydriert ist, startet der Editor automatisch denselben REST-Fetch wie beim `amazon:ASIN`-Trigger.
- Beim Wechsel der ASIN werden alte Hydration-Reste entfernt und der neue Fetch auf `loading` gesetzt.
- Bereits gepflegte redaktionelle Felder wie `titleOverride` und `benefit` werden weiter geschont.

### 2. Safer REST/Server Fallback

- Der REST-Endpunkt versucht weiter zuerst den aus dem Post-Datum abgeleiteten Partner-Tag.
- Falls darueber keine belastbaren Produktdaten kommen, soll ein sicherer Fallback-Link mit Partner-Tag geliefert werden, statt nur `https://www.amazon.de/dp/ASIN`.
- Der Frontend-Renderer gibt ein Bild nur aus, wenn wirklich eine URL vorhanden ist.

### 3. Live-Reparatur fuer bestehende manuelle Card

- Der bestehende Block in `post 21580` wird nach dem Fix neu hydratisiert bzw. direkt mit geladenen Feldern aktualisiert.
- Danach muss der Render-Output einen echten Affiliate-Link mit Tag und entweder ein echtes Bild oder gar keinen Bild-Tag enthalten.

## Verification

- Regressionstest fuer Auto-Hydration bei manueller ASIN.
- Regressionstest, dass kein leeres `img src=""` mehr gerendert wird.
- Live-Check fuer `post 21580` im `content.raw` und `content.rendered`.
