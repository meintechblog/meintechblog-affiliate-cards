# Affiliate Audit Admin Design

## Ziel

Die bestehende Plugin-Seite `Affiliate Card` wird um einen zweiten Tab `Affiliate Audit` erweitert. Dort können Beiträge von `meintechblog.de` der Reihe nach geprüft, geradegezogen und nachvollziehbar protokolliert werden, ohne dass eine zweite Schattenoberfläche außerhalb des Plugins entsteht.

## Leitidee

Die Audit-Oberfläche ist kein technisches Debug-Panel, sondern ein kleines redaktionelles Cockpit. Die Quelle der Wahrheit bleiben die echten WordPress-Beiträge plus Audit-Metadaten am Post. Die Admin-Seite rendert daraus eine verständliche Matrix mit Status, Problemen, Fortschritt und Aktionen.

## Informationsarchitektur

Die bestehende Plugin-Seite bekommt Tabs:
- `Einstellungen`
- `Affiliate Audit`
- optional später `Logs`

Der neue Tab `Affiliate Audit` enthält:
- kompakte Summary-Karten für Statusverteilung
- Filter und Suche
- Matrix der Beiträge, standardmäßig nach neuesten Posts zuerst
- aufklappbare Detailbereiche pro Beitrag
- Aktionsbuttons pro Beitrag

## Matrix-Struktur

Jeder Beitrag wird in genau einer Zeile dargestellt.

Spalten:
- `Datum`
- `Beitrag`
- `Status`
- `Affiliate-Funde`
- `Tracking-ID`
- `Cards`
- `Letzte Prüfung`
- `Kurzlog`
- `Aktionen`

Statuswerte:
- `Offen`
- `Geprüft`
- `Geradegezogen`
- `Manuell prüfen`
- `Fehler`

Anzeigeprinzipien:
- Titel deutlich lesbar und klickbar
- Status als farbige Badges
- Tracking-ID und Card-Abdeckung als knappe Indikatoren
- Kurzlog in menschlicher Sprache statt Roh-API-Text
- Details pro Zeile einklappbar

## Datenmodell

Audit-Daten werden als Post-Meta gespeichert, nicht in einer separaten Tabelle.

Vorgesehene Meta-Felder:
- letzter Audit-Status
- letzter Prüfzeitpunkt
- letzter Geradeziehen-Zeitpunkt
- Anzahl gefundener Affiliate-Stellen
- erkannte ASINs
- Tracking-ID-Befund
- Card-Abdeckungs-Befund
- letzter Kurzlog
- kleine Historie letzter Aktionen

Das Plugin scannt Beiträge live bei Audit-Aktionen und aktualisiert anschließend diese Meta-Felder.

## Aktionslogik

### Prüfen
- scannt den Beitrag auf Amazon-/Affiliate-Stellen
- erkennt Inline-Affiliate-Links, `amazon:ASIN`-Marker und vorhandene Cards
- bewertet Tracking-ID und Card-Abdeckung
- verändert den Beitrag nicht
- aktualisiert Status, Befunde und Kurzlog

### Geradeziehen
- führt zunächst denselben Scan aus
- repariert sichere Fälle automatisiert
- ergänzt fehlende Cards
- vermeidet doppelte oder leere Cards
- zieht Tracking-Links nur dann um, wenn der Zielzustand sauber auflösbar ist
- setzt bei unklaren Fällen nicht blind um, sondern markiert `Manuell prüfen`
- schreibt verständlichen Änderungslog

## Rollout-Strategie

Phase 1:
- neueste Beiträge zuerst listen
- `Prüfen` manuell pro Beitrag
- `Geradeziehen` manuell pro Beitrag
- kein aggressiver Bulk-Lauf

Phase 2:
- nach stabilen Einzelfällen Batch-Aktionen ergänzen
- z. B. `neueste 20 prüfen` oder `offene Beiträge geradeziehen`

## UX-Richtung

Die Oberfläche bleibt WordPress-vertraut, bekommt aber eine klarere, redaktionelle Form:
- Summary-Karten oben
- Filterleiste unter den Karten
- WP-List-Table-artige Matrix
- klare Badges statt schwer lesbarer Textwüsten
- aufklappbare Details mit Linkfundstellen, ASINs und letzter Historie

Wichtige UX-Regeln:
- Fortschritt schnell erfassbar
- keine zweite versteckte Plugin-Welt
- menschlich lesbare Logs
- sichere Automatik, keine aggressive Massenänderung

## Fehlerbehandlung

- unauflösbare Produkte erzeugen keinen Blind-Block
- unklare Tracking-ID-Fälle werden markiert, nicht blind überschrieben
- API-Fehler führen zu Status `Fehler` oder `Manuell prüfen`
- Audit-Log erklärt kurz, was passiert ist und warum gestoppt wurde

## Tests

Benötigt werden Tests für:
- Audit-Statusermittlung aus Beispielbeiträgen
- Erkennung vorhandener Affiliate-Links / Cards / Marker
- korrekte Meta-Aktualisierung nach `Prüfen`
- sichere Inhaltsänderung nach `Geradeziehen`
- Admin-Rendering der Matrix-Grundstruktur

