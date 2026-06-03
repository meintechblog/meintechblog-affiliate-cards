# DESIGN — Availability-Checker (affiliate-cards Komponente 1)

Status: DESIGN (Deliberate Mode, vor Codex-Refute + Bau). 2026-06-03. Owner: meintechblog-master.
Maßgebliche Vorgabe: `meintechblog-master/docs/affiliate-system-concept.md` REVISION (R1–R6 haben Vorrang).
Step 1 (Klick-Tracking) ist LIVE (v0.3.1). Dies ist Step 2.

## Ziel / Anforderung (in Jörgs Worten)
„Regelmäßiger Dead-Link-Checker der tote Affiliate-Artikel markiert — ich repariere nur die kaputten."
Plus (Jörg 09:56): Karten sollen Preis + Lieferstatus zeigen. → **Preis/Status-Anzeige auf den Karten = eigener
Folge-Schritt** (Live-Rendering-Änderung + Amazon-OA-Pflicht „Stand: <Zeit>" → eigener Codex-Refute). Dieser
Step liefert den **Checker-Kern** (Daten + Klassifikation + Cron + REST), nicht die Karten-Anzeige.

## Erfolgskriterium (Oracle, VOR dem Bau)
Nach Deploy: Eich-Stichprobe von 20–30 ASINs mit bekanntem Browser-Status (lieferbar / ausverkauft / Tippfehler-ASIN)
durch den Checker → die berechnete Klasse stimmt für JEDEN mit der Realität überein (insb.: lieferbar→`available`,
ausverkauft→`unavailable_temp`, Quatsch-ASIN→`not_found`, regional gesperrt→`api_inaccessible`, NIE ein lebendes
Produkt fälschlich `not_found`). Erst dann gilt der Klassifikator als vertrauenswürdig (R1).

## Architektur-Entscheidung & verworfene Alternativen
- **Neue, NICHT-werfende Client-Methode `classify_items()`** statt `get_items()` anzufassen.
  Begründung: `get_items()` (a) wirft bei jedem non-200 (kann Auth-Fail nicht von Item-Fehler trennen, R2),
  (b) gibt nur erfolgreich gemappte Items zurück und VERWIRFT das `errors`-Array (braucht man für `not_found`
  vs `api_inaccessible`, R1). `get_items()` wird von der Karten-Hydration genutzt → nicht regredieren.
  *Verworfen:* `get_items()` um Errors erweitern → Blast-Radius auf den Karten-Pfad. Nein.
- **Eigene Tabelle, EINE Quelle der Wahrheit** (R5). Kein `_mtb_affiliate_dead_asins`-Roll-up-Postmeta
  (Invalidierungs-Loch wenn ein ASIN in 50 Posts stirbt/aufersteht). „Welche Posts haben tote ASINs" = Query-Zeit
  (ASIN-Vorkommen × Availability-Status). *Verworfen:* Roll-up-Cache.
- **Resumabler gechunkter Cron** (R4): 1 Tick = 1 Batch (≤10 ASINs), Cursor-Option, Transient-Lock, re-scheduled
  via `wp_schedule_single_event` bis durch; wöchentlicher Kickoff setzt Cursor 0 + snapshottet die ASIN-Liste.
  *Verworfen:* 988 ASINs in einem Tick (PHP-Timeout).
- **Defensives Error-Shape-Parsing**: die exakte Creators-API-Fehlerstruktur für tote ASINs ist unbestätigt
  (Konzept nimmt PA-API5-Codes an). Unbekannte/uneindeutige Antwort → `error` (retry), NIE `not_found`.
  Die Eich-Stichprobe bestätigt die reale Struktur empirisch (Debug-Modus dumpt rohen Payload), dann tunen.

## Datenmodell — Tabelle `{prefix}mtb_affiliate_availability`
```
asin                        varchar(10)  NOT NULL  PRIMARY KEY   -- ASIN ist global, EINMAL pro ASIN
status                      varchar(20)  NOT NULL                -- available|unavailable_temp|not_found|api_inaccessible|error|replaced
price_text                  varchar(64)  NULL                    -- displayAmount, nur bei available
title                       varchar(255) NULL
image_url                   text         NULL
consecutive_unavailable_count int unsigned NOT NULL DEFAULT 0    -- Hysterese (R1): nur „kein Offer"-Runs
first_seen_unavailable_at   datetime     NULL
last_status_change_at       datetime     NULL
checked_at                  datetime     NOT NULL
http_note                   varchar(255) NULL                    -- letzter Fehlercode/Notiz (Debug)
KEY status (status), KEY checked_at (checked_at)
```
Upgrade-Pfad wie Click-Tracker: `create_table()`/`needs_upgrade()`/`maybe_upgrade()` (Transient-Single-Flight-Lock,
Lehre aus v0.3.1 — kein dbDelta-Sturm/Race im boot()-Pfad).

## Klassifikation (R1) — pro ASIN aus `classify_items()`-Rohsignal
`classify_items(asins, ctx)` liefert `{auth_ok, http_status, items{asin→{price_text,title,image_url,detail_url}}, errors{asin→code}}` (wirft NICHT).
- `auth_ok === false` (Token-Request scheiterte) → **GANZEN SCAN ABBRECHEN**, Alarm-Option setzen, NICHTS pro ASIN
  schreiben (R2 — Account-Problem darf nicht in 988 „verdächtig" kippen).
- `http_status !== 200` (aber auth_ok) → ganzer Batch = `error` (retry), nichts als tot werten.
- ASIN in `items` mit `price_text != null` → **available**.
- ASIN in `items`, `price_text == null` → **unavailable_temp** (kein Buybox-Offer; ≠ dauerhaft tot).
- ASIN in `errors`, Code enthält `InvalidParameterValue` → **not_found** (einziges eindeutiges Tot-Signal).
- ASIN in `errors`, Code enthält `ItemNotAccessible` (oder anderer Item-Fehler) → **api_inaccessible** (NIE tot; manueller Review-Bucket).
- ASIN weder in items noch in errors → **error** (uneindeutig → retry, sicherer Fail-Mode).

### Hysterese-Update (pro Upsert)
- neu `available`: `consecutive_unavailable_count=0`, `first_seen_unavailable_at=NULL`.
- neu `unavailable_temp`: count++ (war vorher schon unavailable_temp), sonst count=1 + `first_seen_unavailable_at=now`.
- `not_found`: sofort Reparatur-Kandidat (eindeutig), aber immer human-gated (R6) — kein Auto-Apply.
- `api_inaccessible`: eigener Review-Bucket, NIE Reparatur-Kandidat.
- `error`: Status nur als `error` markieren wenn vorher kein „besserer" Status existierte; NIE Hysterese-Count/lebenden Status zerstören (ein transienter API-Fehler darf einen `available` nicht überschreiben → bei `error` den letzten guten Status BEHALTEN, nur `checked_at`/`http_note` updaten).
- `last_status_change_at` nur setzen wenn sich `status` real ändert.

### Reparatur-Kandidaten (Query, R6 human-gated)
`status='not_found'` ODER (`status='unavailable_temp'` AND `consecutive_unavailable_count >= 3`).
`api_inaccessible` separat als „manueller Review".

## Cron (R4) — resumabel + auth-abort
- Wöchentlicher Kickoff `mtb_affiliate_availability_kickoff`: snapshottet ASIN-Worklist in Option
  `mtb_affiliate_availability_worklist` (Array), Cursor=0, schedule single-event-Tick.
- Tick `mtb_affiliate_availability_tick`: Transient-Lock (gegen Doppellauf); nimm `worklist[cursor..cursor+10]`,
  `classify_batch`; bei AuthAbort → stop + `mtb_affiliate_availability_auth_alarm`-Option + KEIN reschedule;
  sonst Cursor+=10, reschedule single-event (kurze Verzögerung) bis Cursor≥len(worklist).
- ASIN-Worklist-Quelle: `collect_asins()` = $wpdb-Scan publizierter Posts (`post_content` regex auf
  `amazon:ASIN`, `/dp/ASIN`, `/gp/product/ASIN`) → unique → deckt sich mit der 988er-Worklist.
- partner_tag: registrierter Tag Pflicht (sonst Stub). Default `meintechblog-230807-21` (138× genutzt, registriert),
  überschreibbar; Tag egal für Verfügbarkeit (nur Attribution), MUSS aber registriert sein.

## REST (Namespace `mtb-affiliate-cards/v1`)
- `GET /availability` (auth edit_posts): Zeilen + Filter `?status=` und `?repair=1` (= Reparatur-Kandidaten-Query).
- `GET /availability/posts?repair=1` (auth): Query-Zeit-JOIN — welche Posts enthalten Reparatur-Kandidaten (R5, kein Cache).
- `POST /availability/scan` (auth manage_options): Body optional `{asins:[…], debug:bool}`.
  - mit `asins` → synchroner Eich-/Ziel-Scan, gibt Klassifikation (+ bei `debug` rohen Payload) zurück. KEINE Worklist nötig.
  - ohne `asins` → Cron-Kickoff anstoßen (Worklist-Snapshot + Tick schedulen), gibt `{queued, worklist_size}`.

## Versionierung / Tests
- v0.3.1 → **v0.4.0** (feat). CHANGELOG + Tag.
- `tests/test-availability-checker.php` im Bestands-Stil: Mock-Transport → alle 5 Klassen + Hysterese-Übergänge
  + Auth-Abort (kein per-ASIN-Write) + „error überschreibt available NICHT".

## Verifikation (R31, nach Deploy)
1. php -l + Test-Suite grün (Mock).
2. Deploy FTPS (dependency-sicher: neue Klasse zuerst, plugin.php zuletzt; Tabelle via maybe_upgrade()).
3. Site 200, Version 0.4.0 live, `GET /availability` unauth→401, route registriert.
4. **Eich-Stichprobe** `POST /availability/scan {asins:[…20-30…], debug:true}` → Klassen gegen bekannte Realität
   prüfen (Oracle oben). Bei Shape-Abweichung Parser tunen + re-verify. Erst dann „done".

---

# REVISION nach Codex-Refute (2026-06-03) — hat Vorrang vor widersprechenden Stellen oben

Codex (gpt-5-codex) hat das Design refute-gesparrt. Übernommene Korrekturen, schwerste zuerst:

## RC1 (BLOCKER) — Auth-Signal HTTP-status-getrieben, nicht nur Token
`classify_items()` gibt `auth_state` zurück (kein bool): `ok | token_failed | access_revoked | rate_limited | transient_error`.
- Token-Endpoint non-200 → `token_failed`.
- Catalog **401/403** → `access_revoked` (Eligibility unter 10/30 gefallen, Token gültig — DAS war das Loch).
- Catalog **429** → `rate_limited`.
- Catalog **5xx/Netzwerk** → `transient_error`.
- Catalog **200** → `ok`, items+errors parsen.
Checker-Reaktion: `token_failed`/`access_revoked` → **SCAN HART ABBRECHEN + Alarm-Option**, KEIN per-ASIN-Write (R2).
`rate_limited` → Tick stoppen, Cursor NICHT vorrücken, später re-schedulen (Backoff). `transient_error` → Batch=error,
letzten guten Status BEHALTEN, Cursor vorrücken (nächster Wochenlauf retry'd).
**Backstop:** N aufeinanderfolgende Batches nur `error`/`transient` UND 0 `available` → Alarm, auch ohne hartes Auth-Signal
(fängt „still zur No-Op verkommen" unabhängig vom Amazon-Statuscode).

## RC2 (MAJOR) — Stub-/Config-Guard VOR Klassifikation
Pre-flight: client_id/secret leer ODER partner_tag leer/NICHT-registriert → **config_error-Alarm, Scan abbrechen, NICHT klassifizieren**
(Stub-200 mit Platzhaltern würde sonst 988× fälschlich `unavailable_temp`). Stub-Modus == auth-fail-äquivalent.
Eich-Stichprobe MUSS mit echtem registriertem Tag laufen + als **Stub-Guard-Assertion**: ein bekannt-lebender ASIN → `available`.

## RC3 (MAJOR) — Klassifikation: positives „kein Offer" statt price==null
- `available`: Item da UND Offers-Struktur DA UND kaufbarer Preis.
- `unavailable_temp`: Item da UND Offers-Struktur DA aber leer/kein kaufbares Listing (POSITIV bestätigt „kein Offer").
- `error` (NICHT unavailable_temp): Item da aber Offers-Struktur ABWESEND/unparsebar, ODER degradiertes Item (nur asin, title null)
  → transient, Status behalten. (Trennt Parser-Shape-Mismatch von echtem „ausverkauft".)
- `not_found`: ASIN in **per-ASIN**-errors mit `InvalidParameterValue` — NUR wenn errors per-ASIN gekeyt sind. Sind errors
  request-level → ganzer Batch `transient_error`, NIE `not_found`.
- `api_inaccessible`: per-ASIN `ItemNotAccessible` (o.a. Item-Fehler) → Review-Bucket, nie tot.

## RC4 (MAJOR→Gate) — Eich-Stichprobe = HARTES GATE mit benannten Shape-Assertions
Defensiv bauen, dann via Live-Eich-Stichprobe (debug:true, roher Payload) EMPIRISCH bestätigen, BEVOR der Klassifikator
getraut wird. Pflicht-Assertions: (a) ist `errors` per-ASIN oder per-request gekeyt? (b) exakter Offers-Feldpfad
(price.displayAmount vs price.money.displayAmount …)? (c) bekannt-ausverkauft → leere-listings vs abwesende-offers?
(d) bekannt-lebend → `available` (Stub-Guard). Bei Shape-Abweichung Parser tunen + re-verify. Erst dann „done".

## RC5 (MINOR) — Cron: Option-Lock + Watchdog + idempotenter Kickoff
Lock NICHT als nackter Transient (kein atomarer Mutex auf Object-Cache), sondern Option `{owner, started_at}`:
abgelaufener Lock (älter als max-Tick-Dauer) wird reklamiert = Watchdog gegen stuck-scan. Kickoff idempotent: läuft ein
Scan (Cursor mitten drin) → resumen/ablehnen, NIE parallel starten. `collect_asins()` leer/fehlerhaft → Kickoff abbrechen,
gute Worklist NICHT überschreiben. Tick-Spacing definiert (≥3 s), 429 → eigener Backoff.

## RC6 (MINOR) — ASIN-Quelle = Audit-Extractor (eine Quelle)
`collect_asins()` nutzt `MTB_Affiliate_Audit_Service::scan_post_content()` über publizierte Posts (deckt amazon:ASIN-Marker
+ /dp/ASIN-Links inkl. Card-Block-detail_urls), NICHT eine divergente 3-Regex. Eich-Stichprobe enthält 1 Card-Block-only-ASIN.

## RC7 (MINOR) — Hysterese-Kanten
`not_found→available` (Wiederauferstehung): count=0 + `first_seen_unavailable_at=NULL`. `replaced` = terminal,
aus der Worklist AUSGESCHLOSSEN. `transient_error` lässt Status + count + first_seen UNANGETASTET (nur checked_at/http_note).

## NICHT in diesem Step (Folge-Schritte, dokumentiert)
- Karten zeigen Preis + „Stand: <Zeit>" (Live-Rendering + Amazon-OA-Compliance) → eigener Step + Codex-Refute.
- Repair-Loop-Ausführung (R6, human-gated Review-Bucket in /affiliate) → nach Webapp /affiliate (Step 3).
- Voll-Scan der 988 ASINs produktiv → via wöchentlichem Cron, nachdem die Eich-Stichprobe den Klassifikator bestätigt.
