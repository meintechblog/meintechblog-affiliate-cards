# Inline Affiliate Flow Design

**Date:** 2026-03-17

## Goal

Affiliate-Hinweise im Fließtext sollen ohne rohe Amazon-URLs oder manuelle Card-Bastelei funktionieren: Redakteure schreiben Inline-Tokens wie `amazon:B0CK3L9WD3`, und das Plugin wandelt sie in verlinkten Text plus nachgelagerte `Affiliate Card`-Blöcke um.

## Context

Das Plugin kann heute bereits einzelne `Affiliate Card`-Blöcke über einen eigenen Absatz mit `amazon:ASIN` erzeugen und direkt hydrieren. Für alte und neue Beiträge fehlt aber noch der komfortable Fließtext-Workflow:

- Affiliate-Produktbezug soll direkt im Satz formuliert werden können
- der sichtbare Inline-Linktext soll automatisch aus Amazon-Titeln entstehen
- die eigentliche klickstarke Produktkarte soll direkt unter dem betroffenen Absatz auftauchen
- bestehende funktionierende Affiliate-Tags dürfen dabei nicht blind kaputtgeschrieben werden

Der konkrete Testfall ist der Beitrag `post=21052` (`Howto: UniFi Protect Videofeed in Loxone einbinden`), in dem bereits Affiliate-Kontext im Satz vorkommt.

## Approaches Considered

### 1. Save-time Enrichment auf Absatzebene

Beim Speichern scannt das Plugin Absatzblöcke auf Inline-Marker oder vorhandene Amazon-Produktlinks und erzeugt bzw. aktualisiert direkt darunter einzelne `Affiliate Card`-Blöcke.

**Pros**
- passt zu alten und neuen Beiträgen
- funktioniert ohne zusätzlichen Redaktions-Klick
- hält Fließtext und Card eng beieinander

**Cons**
- erfordert saubere Duplikat- und Aktualisierungslogik
- braucht konservative Grenzen, damit kein Text kaputttransformiert wird

### 2. Editor-Hinweis mit manueller Umwandlung

Das Plugin erkennt Inline-Affiliate-Kontext, schlägt aber nur eine Umwandlung vor.

**Pros**
- geringeres Risiko
- mehr Kontrolle für Redakteure

**Cons**
- mehr Reibung
- schwächerer Hebel für alte Beiträge

### 3. Aggressive Inline-Umbauten der bestehenden Sätze

Das Plugin versucht, frei formulierte Satzteile automatisch in Affiliate-Links zu transformieren.

**Pros**
- maximal automatische Redaktion

**Cons**
- hohes Risiko für falsche Linkspannen
- unnötig fragil

## Decision

Wir setzen **Ansatz 1** um, aber mit strikten Regeln:

- authoring marker im Satz ist `amazon:ASIN`
- nur `core/paragraph`-Blöcke werden automatisch verarbeitet
- der restliche Satz bleibt unangetastet
- pro erkanntem Absatz werden einzelne `Affiliate Card`-Blöcke direkt darunter erzeugt

## Authoring Model

### Inline-Marker

Redakteure schreiben im Fließtext direkt `amazon:ASIN`, zum Beispiel:

`Ich bin begeistert vom amazon:B0CK3L9WD3, weil er ...`

### Umwandlung

Wenn der Absatz abgeschlossen wird oder der Beitrag gespeichert wird:

- der Marker wird durch `Amazon-Titel (Affiliate-Link)` ersetzt
- dieser gesamte Teil wird mit dem passenden Amazon-Link verknüpft
- direkt unter dem Absatz wird eine `Affiliate Card` für die ASIN eingefügt

### Mehrere Produkte im selben Absatz

Wenn mehrere Marker im selben Absatz vorkommen:

- alle Marker werden inline ersetzt
- darunter werden mehrere einzelne `Affiliate Card`-Blöcke erzeugt
- die Reihenfolge der Cards entspricht der Marker-Reihenfolge im Absatz

## Tracking-ID Strategy

- Das Plugin extrahiert aus jeder erkannten Produktreferenz die ASIN und einen eventuell vorhandenen bestehenden Tag
- Aus dem Post-Datum wird der gewünschte Datums-Tag abgeleitet
- Der Datums-Tag wird nur verwendet, wenn er für den Produktaufruf gültig ist
- Ist der Datums-Tag nicht valide, gewinnt ein bereits funktionierender Tag oder ein sicherer Fallback
- Der Inline-Satz wird in Phase 1 nicht aggressiv rückwirkend umgeschrieben, wenn bereits ein funktionierender Amazon-Link existiert

## Block Placement and Update Rules

- Cards werden direkt nach dem verarbeitenden Absatz eingefügt
- Für dieselbe ASIN direkt unter demselben Absatz wird aktualisiert statt verdoppelt
- Wenn eine Card manuell gelöscht wurde, darf sie beim nächsten Speichern erneut entstehen, solange der Marker oder der zugehörige Affiliate-Link im Absatz noch vorhanden ist
- Mehrfachvorkommen derselben ASIN im selben Absatz erzeugen nur eine Card

## Safety Rules

- nur `core/paragraph`
- nur klar erkennbare Marker `amazon:ASIN` oder bereits vorhandene Amazon-Produktlinks
- keine freie Sprachinterpretation von Produktnamen
- wenn Amazon-Daten nicht geladen werden können, bleibt der Marker oder bestehende Text erhalten; kein kaputter Blind-Link
- wenn Tracking-Prüfung fehlschlägt, wird konservativ auf den letzten funktionierenden Zustand gefallen

## Error Handling

- Amazon liefert keinen Titel: Marker bleibt stehen oder bestehender Inline-Link bleibt unverändert
- Amazon liefert keine Bild-/Produktdaten: keine Card-Erzeugung, aber auch keine Zerstörung des Absatzes
- ungültige Datums-Tracking-ID: bestehender funktionierender Tag oder Fallback-Tag
- identische ASIN schon direkt unter dem Absatz vorhanden: Update statt Duplikat

## Success Criteria

- `amazon:ASIN` im Fließtext wird zuverlässig zu verlinktem Produkttitel plus `(Affiliate-Link)`
- unter dem Absatz entsteht automatisch eine einzelne `Affiliate Card` pro Produkt
- mehrere Marker im Absatz erzeugen mehrere Cards in derselben Reihenfolge
- funktionierende bestehende Affiliate-Links werden nicht kaputtoptimiert
- der Testbeitrag `post=21052` kann als Referenzfall sauber automatisch angereichert werden
