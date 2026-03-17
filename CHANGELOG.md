# Changelog

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
