# Changelog

## 0.2.12

- erweitert den Save-Flow um Inline-Affiliate-Enrichment: `amazon:ASIN` im Fließtext wird zu verlinktem `Titel (Affiliate-Link)` umgeschrieben
- erzeugt direkt unter dem betroffenen Absatz einzelne `Affiliate Card`-Blöcke pro Produkt statt nur rohe Marker zu sammeln
- verhindert Doppelblöcke bei bereits vorhandenen benachbarten Cards und fällt bei abgelehnten Datums-Tags konservativ auf bestehende funktionierende Tracking-IDs zurück

## 0.2.11

- richtet ASIN-Feld und Badge im Editor in einer gemeinsamen Header-Zeile aus, damit sich beides nicht mehr ueberlappt
- ersetzt die rohen `<`/`>`-Bildknuepfe durch echte Chevron-Icon-Buttons mit sauberem Hover- und Disabled-State
- zieht die Bildflaeche nach oben nach, damit der Header mehr Luft bekommt und die Editor-Card ruhiger wirkt

## 0.2.10

- zieht die editierbare ASIN als zentrales Feld ganz nach oben in die Editor-Card
- benennt die Inline-Felder auf `Titel` und `Beschreibung` um und entfernt die doppelte Textvorschau darunter
- rendert den unteren CTA im Editor als echten Amazon-Link und poliert die Bildnavigation optisch auf

## 0.2.9

- zeigt die ASIN jetzt sichtbar direkt in der `Affiliate Card` im Editor
- nutzt fuer den Bildwechsel kompakte `<`- und `>`-Buttons statt lange Textlabels
- fuellt das Titelfeld mit dem aktuell sichtbaren Kartentitel und leert den Override wieder sauber, wenn man auf den Basistitel zurueckgeht

## 0.2.8

- zieht die `Affiliate Card` im Gutenberg-Editor optisch deutlich naeher an die echte Live-Karte
- verlegt Badge-, Titel-, Nutzen- und Bildsteuerung in die Kartenansicht statt in eine Formularbox
- zeigt Lade- und Fehlerzustaende jetzt direkt in der Karte statt als lose Editor-Hinweise

## 0.2.7

- zeigt in der Editor-Vorschau nach der Hydration jetzt den echten Produkttitel statt auf die rohe ASIN zurueckzufallen

## 0.2.6

- hydriert neue `Affiliate Card`-Bloecke direkt im Editor nach `amazon:ASIN` statt nur die ASIN stehen zu lassen
- fuegt In-Block-Steuerung fuer Badge, Kurztitel, Nutzenzeile, Bildwechsel und Retry bei Ladefehlern hinzu
- laesst im Frontend bewusst gespeicherte Titel-, Link- und Bildentscheidungen gewinnen, statt sie wieder vom Live-Fetch ueberschreiben zu lassen

## 0.2.5

- zieht den Editor-Flow auf echte Einzelkarten um: ein `Affiliate Card`-Block steht jetzt fuer genau ein Produkt
- stellt user-facing Naming in Block, Settings und Doku auf `Affiliate Card` singular um
- haelt bestehende Alt-Bloecke im Editor erkennbar, damit sie bei Bedarf in einzelne Karten aufgeteilt werden koennen

## 0.2.4

- gibt dem dynamischen Affiliate-Block den nötigen WordPress-Post-Kontext (`postId`, `postType`) für Live-Enrichment neuer Produkte
- behebt fehlende Titel-, Bild- und Trackingdaten bei frisch erzeugten `amazon:ASIN`-Blocks

## 0.2.3

- ersetzt das fragile Save-Post-Token-Scannen durch einen expliziten Gutenberg-Editor-Trigger für exakte `amazon:ASIN`-Absätze
- wandelt Token-Blöcke direkt im Editor beim Commit in native Affiliate-Blocks um
- verhindert dabei doppelte Produktblöcke im selben Beitrag über eine Editor-Notice

## 0.2.2

- bestehende Affiliate-Karten bleiben beim ASIN-Autoscan erhalten und werden um neue Produkte ergänzt statt überschrieben
- doppelte ASIN-Marker werden sauber dedupliziert, ohne angereicherte Kartendaten zu verlieren

## 0.2.1

- speichert Amazon-Titel, Bilder, Ziel-Links und Nutzenzeilen jetzt direkt beim ASIN-Autoscan in den Block
- reduziert die Abhängigkeit von Live-Amazon-Requests im Frontend deutlich

## 0.2.0

- persistente Plugin-Settings für CTA, Badge-Modus, Marketplace und Amazon-Credentials
- `save_post`-Autoscan für eigenständige ASIN-Absätze
- nativer Post-Processor zum Erzeugen/Aktualisieren des `meintechblog/affiliate-cards`-Blocks
- PHP-Client für die Amazon Creators API inklusive Tracking-ID-Ableitung
- `uninstall.php` und ZIP-Build-Skript für normales Plugin-Lifecycle-Verhalten
- zusätzliche lokale Tests für Settings, Processor, Amazon-Client und Lifecycle

## 0.1.0

- initiales öffentliches Repo für `meintechblog-affiliate-cards`
- Plugin-Bootstrap und Gutenberg-Block-Skeleton
- Renderer im aktuellen Live-Kartenstil
- Titelkürzung, Badge-Resolver und Token-Scanner als Kernlogik
- lokale PHP-Checks für Kern- und Block-Basis
