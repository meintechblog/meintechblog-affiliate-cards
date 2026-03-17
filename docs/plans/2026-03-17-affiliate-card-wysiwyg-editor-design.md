# Affiliate Card WYSIWYG Editor Design

**Date:** 2026-03-17

## Goal

Die `Affiliate Card` soll im Gutenberg-Editor nahezu so aussehen wie im Frontend, damit Redaktionsarbeit direkt an der echten Kartenoptik statt an einer technischen Formularbox stattfindet.

## Context

Der aktuelle Block ist funktional: `amazon:ASIN` hydriert direkt im Editor, Titel und Bilder kommen von Amazon, Badge und Texte sind bearbeitbar. Die Editor-Ansicht ist aber noch stark formularlastig und weicht sichtbar von der echten Kartenansicht im Frontend ab.

## Approaches Considered

### 1. Echte WYSIWYG-Karte im Editor

Der Editor rendert eine fast vollständige Live-Kartenoptik mit kleinen, eingebetteten Bearbeitungsstellen.

**Pros**
- Redakteure arbeiten direkt am späteren Look
- Weniger mentale Übersetzung zwischen Backend und Frontend
- Bessere Qualitätskontrolle bei Titel-, Bild- und Badge-Auswahl

**Cons**
- Editor-Markup und Frontend-Markup müssen bewusst synchron gehalten werden
- Mehr CSS-Abstimmung zwischen Editor und Frontend

### 2. Geteilte Ansicht mit Vorschau plus Formular

Oberhalb eine Karte, darunter technische Felder.

**Pros**
- Einfacher umzusetzen
- Geringes Risiko für bestehende Bearbeitungslogik

**Cons**
- Bleibt wie ein Plugin-Backend
- Doppelte Oberflächenlogik

### 3. Nahezu vollständiger Frontend-Klon

Der Editor übernimmt die Frontend-Karte fast 1:1 und blendet Controls nur bei Auswahl ein.

**Pros**
- Maximale visuelle Übereinstimmung

**Cons**
- Höhere Komplexität
- Mehr Risiko für Gutenberg-spezifische Bedienprobleme

## Decision

Wir setzen **Ansatz 1** um: eine echte WYSIWYG-Karte im Editor, die sich eng am Frontend orientiert, aber weiterhin klar und robust editierbar bleibt.

## Editor Experience

### Grundaufbau

- Badge oberhalb des Bilds wie im Frontend
- Bildbereich in echter Kartenoptik
- Titel, Nutzenzeile und CTA an ihrer echten visuellen Position
- Farben, Abstände und Button-Optik nahe an der Live-Ausgabe
- Dark-/Light-Verhalten soweit sinnvoll an die Frontend-Karte angelehnt

### Sichtbare Inline-Controls

- Badge als kleines Dropdown direkt im Badge-Bereich
- Kurztitel an der Position des sichtbaren Titels editierbar
- Nutzenzeile an der Position der sichtbaren Nutzenzeile editierbar
- Bildwechsel über Links-/Rechts-Buttons direkt am Bild

### Inspector / Sidebar

Die Sidebar bleibt für technische und sekundäre Funktionen zuständig:

- ASIN
- manuelles Reload der Produktdaten
- eventuelle technische Diagnosen oder Fallback-Zustände

Der CTA bleibt global gesteuert und wird nicht als frei editierbares Inline-Feld gezeigt.

## Interaction Model

- Standardzustand: eine ruhige, fast echte Live-Karte
- Ausgewählter Block: editierbare Stellen werden dezent hervorgehoben
- Kein harter Wechsel zwischen Preview und Formularmodus
- Ladezustände erscheinen als Karten-Skeleton innerhalb des Layouts
- Fehlerzustände bleiben in Kartenform sichtbar und bieten eine klare Retry-Aktion

## Data Model

Der bestehende hydratisierte Datenfluss bleibt erhalten:

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

Die neue Editor-Oberfläche ist also vor allem ein Render-/Interaction-Umbau, kein Architekturwechsel.

## Implementation Shape

- Die Frontend-Kartenstruktur wird als visuelle Vorlage für den Editor genutzt
- `index.js` rendert statt der Formularbox eine editorfähige Kartenansicht
- `editor.css` wird auf echte Kartenoptik umgebaut
- Die Hydration-Logik bleibt intakt und wird nur optisch besser eingebettet

## Success Criteria

- Eine `Affiliate Card` wirkt im Editor fast wie die veröffentlichte Karte
- Titel, Nutzenzeile, Badge und Bild lassen sich direkt in der Karte pflegen
- Lade- und Fehlerzustände bleiben klar, aber stören die Kartenoptik nicht
- Der bestehende `amazon:ASIN`-Hydration-Flow bleibt funktionsfähig
