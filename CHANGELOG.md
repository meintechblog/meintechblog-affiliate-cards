# Changelog

## 0.2.30

- hydriert manuell eingefuegte Affiliate Cards jetzt automatisch, sobald eine gueltige ASIN eingetragen wird
- ordnet geladene Produktdaten per `hydratedAsin` sauber der aktuellen ASIN zu, damit beim Wechsel der ASIN keine alten Titel-, Bild- oder Linkreste stehen bleiben
- rendert im Frontend keine leeren `img src=""`-Platzhalter mehr
- versucht bei REST-Hydration nach einem ungueltigen Datums-Partner-Tag zusaetzlich einen funktionierenden Partner-Tag aus aktuellen Beitraegen, statt sofort auf leere Fallback-Daten zu kippen

## 0.2.29

- begrenzt den `save_post`-Pfad jetzt auf explizite `amazon:`-Marker statt normale bestehende Amazon-Links bei jedem Speichern erneut anzufassen
- verhindert damit, dass Beiträge mit bereits gesetzten Inline-Affiliate-Links und Cards bei normalen Speichervorgängen unnötig umgeschrieben oder in Sonderfällen beschädigt werden

## 0.2.28

- serialisiert automatisch erzeugte Affiliate-Card-Blöcke jetzt mit explizitem öffnenden und schließenden Gutenberg-Blockkommentar statt als selbstschließenden Kommentar
- erkennt beim Einlesen weiterhin beide Formen, damit bestehende Beiträge und neue Geradeziehen-Läufe stabil bleiben

## 0.2.27

- führt im Audit den Status `Legacy-Fall` für alte Affiliate-Beiträge ein, die heute nicht mehr sicher automatisch in Cards umgewandelt werden können
- zeigt diese Fälle separat in Summary, Filter und Kurzlog statt sie wie normale offene Beiträge wirken zu lassen

## 0.2.26

- klassifiziert Beiträge ohne Affiliate-Funde im Audit jetzt als unkritisch statt als `Unklar`
- zeigt für solche Beiträge im Kurzlog klar `Keine Affiliate-Funde`

## 0.2.25

- bewertet bestehende Amazon-Links jetzt gegen den aktuellen Amazon-Titel und unterscheidet zwischen weichem Titel-Mismatch und hartem Fehltreffer
- übernimmt bei weichen Mismatches den vorhandenen Beitrags-Linktext als Card-Titel, damit kurze redaktionelle Produktnamen erhalten bleiben
- blockiert bei harten Fehltreffern die automatische Card-Erzeugung, statt offensichtliche Fehlprodukte wie Kreditkarten in alte Technik-Beiträge einzubauen

## 0.2.24

- baut für alte Amazon-Affiliate-Links jetzt eine Fallback-Card aus vorhandenem Linktext und URL, wenn die Creators API keine Produktdaten mehr liefert
- verhindert damit, dass ältere Beiträge komplett ohne Card bleiben, nur weil Amazon den Artikel nicht mehr sauber auflöst

## 0.2.23

- dedupliziert automatisch erzeugte Affiliate-Cards jetzt beitragsweit pro ASIN, damit wiederholte Produktlinks in späteren Absätzen nicht jedes Mal dieselbe Karte erneut erzeugen
- lässt die späteren Inline-Links dabei normal verlinkt stehen, sodass der Textfluss vollständig bleibt und nur die Kartenmenge ruhiger wird

## 0.2.22

- verarbeitet Inline-Affiliate-Links jetzt auch in klassischen HTML-Absätzen mit nackten `<p>...</p>` statt nur in `core/paragraph`-Blöcken
- erkennt für die Intro-Deferral-Logik neben dem Gutenberg-`more`-Block jetzt auch rohe `<!--more-->`-Marker älterer Beiträge

## 0.2.21

- validiert beim Tracking-Normalisieren den aus dem Beitragsdatum abgeleiteten Amazon-Tag jetzt erst gegen echte Produktdaten
- fällt bei ungültigem Datums-Tag konservativ auf den bereits funktionierenden bestehenden Tag zurück, statt Links und Cards kaputt auf einen nicht gemappten Partner-Tag umzuschreiben

## 0.2.20

- behebt einen Dedupe-Fehler im Post-Processor, der Absätze zwischen mehreren automatisch erzeugten Affiliate-Cards verschlucken konnte
- erhält damit bei mehreren Amazon-Links in aufeinanderfolgenden Absätzen den Fließtext und die lokale Kartenreihenfolge stabil

## 0.2.19

- verschiebt automatisch erzeugte Affiliate-Cards für Amazon-Verweise aus der Einleitung jetzt hinter den ersten `more`-Tag, damit oberhalb des Teasers keine Produktboxen den Einstieg zerschießen
- bewahrt dabei die echte Reihenfolge gemischter Amazon-Verweise im Absatz, auch wenn bestehende Amazon-Links und `amazon:ASIN`-Marker zusammen vorkommen

## 0.2.18

- `Geradeziehen` vereinheitlicht jetzt bestehende Amazon-Produktlinks und benachbarte Affiliate-Card-URLs konsequent auf den aus dem Beitragsdatum abgeleiteten Tracking-Tag
- behebt damit Altfälle, in denen Inline-Link und Karte unterschiedliche, aber formal gültige Tracking-IDs trugen und der Audit-Tab fälschlich auf `Manuell prüfen` hängen blieb

## 0.2.17

- normalisiert gespeicherte Amazon-Block-URLs mit `\u0026`, damit der Audit-Parser korrekte Tracking-Tags aus Block-JSON liest
- verhindert dadurch falsche `Tracking-ID abweichend`-Befunde bei bereits sauber gepflegten Affiliate Cards

## 0.2.16

- erweitert die bestehende Plugin-Settings-Seite um den neuen Tab `Affiliate Audit`
- listet neueste Blogposts mit Status, Affiliate-Funden, Tracking-Befund, Card-Anzahl und lesbarem Kurzlog
- fuegt echte Admin-Aktionen `Prüfen` und `Geradeziehen` ueber `admin-post.php` samt Ruecksprung und Status-Notice hinzu
- poliert den Audit-Tab mit eigener Admin-CSS und Status-/Tracking-Badges

## 0.2.15

- trennt Inline- und Standalone-ASINs im Post-Processor sauber, damit beim erneuten Speichern eines bereits angereicherten Absatzes kein zusätzlicher Sammelblock mehr entsteht
- hält bestehende Einzel-Cards unter Inline-Absätzen beim Re-Save stabil statt neue doppelte Blocks zu erzeugen
- ergänzt einen Re-Save-Regressionscheck für bereits verlinkte Absätze mit benachbarten Affiliate-Cards

## 0.2.14

- stoppt asin-only Fallbacks schon im Save-Resolver, damit gemischte Absätze mit gültigen und ungültigen `amazon:ASIN`-Markern nur noch für echte Treffer Cards erzeugen
- lässt unaufgelöste Produkte beim Speichern komplett aus der Card-Erzeugung heraus, statt leere Platzhalter weiterzureichen
- ergänzt einen Plugin-Lifecycle-Regressionscheck für partielle Resolver-Ergebnisse

## 0.2.13

- verhindert leere Inline-`Affiliate Card`-Blöcke für `amazon:ASIN`-Marker, wenn Amazon zu einer ASIN keine auflösbaren Produktdaten liefert
- lässt unaufgelöste Marker im Absatz sichtbar stehen, statt darunter kaputte Platzhalter-Cards zu erzeugen
- ergänzt einen Regressionstest für gemischte Absätze mit gültigen und ungültigen Inline-Markern

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
