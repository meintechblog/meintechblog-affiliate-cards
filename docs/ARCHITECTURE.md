# Architektur

## Kernidee

Das Plugin trennt Editor-UX, Renderlogik und Amazon-Datenzugriff sauber:

- **Editor**: Gutenberg-Block mit nativem Bediengefühl
- **Render**: dynamischer PHP-Block für stabiles Frontend
- **Daten**: Amazon Creators API mit Caching

## Wichtige Bausteine

### `MTB_Affiliate_Title_Shortener`

- erzeugt kurze, lesbare Produkttitel
- bevorzugt redaktionelle Overrides für bekannte ASINs
- dient als Grundlage für spätere automatische Kürzung

### `MTB_Affiliate_Badge_Resolver`

- entscheidet zwischen:
  - `Im Video verwendet`
  - `Passend zu diesem Setup`
- im Auto-Modus auf Basis des Post-Inhalts

### `MTB_Affiliate_Token_Scanner`

- scannt eigenständige Absatzblöcke mit:
  - `B0D7955R6N`
  - `amazon:B0D7955R6N`
- entfernt diese Marker aus dem Inhalt
- liefert die gesammelten ASINs für die Block-Erzeugung

### `MTB_Affiliate_Renderer`

- rendert das aktuelle Kartenlayout
- nutzt die warme Farbwelt von meintechblog
- unterstützt Dark-Mode-Selektoren

### `MTB_Affiliate_Block`

- registriert den Gutenberg-Block
- bindet Editor-Script und Styles ein
- nutzt ein dynamisches PHP-Template für die Ausgabe

## Zielarchitektur für Version 1

1. Beitrag wird gespeichert
2. Scanner findet alleinstehende ASIN-Textblöcke
3. Plugin erzeugt/aktualisiert `Affiliate Cards`
4. PHP holt Produktdaten aus der Creators API
5. Renderlogik baut den finalen Kartenblock

## Offene Integrationen

- Creators-API-Service in PHP
- WordPress-Options-Handling für Credentials
- Save-Hooks und Post-Block-Mutation
- Caching per Transients
