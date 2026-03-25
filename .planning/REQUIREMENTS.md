# Requirements: meintechblog Affiliate Cards

**Defined:** 2026-03-25
**Core Value:** Affiliate-Produkte in unter 30 Sekunden von Telegram-Nachricht in WordPress-Blogpost

## v1.0 Requirements

Requirements for Telegram-to-WordPress Affiliate Pipeline. Each maps to roadmap phases.

### Telegram Bot

- [x] **TGBOT-01**: Plugin empfaengt Telegram-Nachrichten via Webhook und validiert Secret-Token
- [x] **TGBOT-02**: Bot antwortet mit fertigem Affiliate-Link (ASIN + aktuelle Tracking-ID)
- [x] **TGBOT-03**: Bot loest ShortURLs auf (amzn.to / amzn.eu) und extrahiert ASIN
- [x] **TGBOT-04**: Bot akzeptiert direkte ASINs (B0XXXXXXXX) und Amazon-Produkt-URLs
- [x] **TGBOT-05**: User kann Tracking-ID per Datum setzen (heute, YYMMDD, DD.MM.YY/YYYY)
- [x] **TGBOT-06**: User kann Tracking-ID per "reset" auf Default zuruecksetzen
- [x] **TGBOT-07**: Bot filtert optional nach Chat-ID (konfigurierbar in Settings)

### Produkt-Bibliothek

- [x] **PLIB-01**: Empfangene Produkte werden automatisch in Custom DB Table gespeichert
- [x] **PLIB-02**: Produkt-Eintrag enthaelt ASIN, Titel, Detail-URL, Bild-URL, Empfangsdatum
- [x] **PLIB-03**: REST API Endpoint liefert gespeicherte Produkte (sortiert nach Datum, neueste zuerst)
- [ ] **PLIB-04**: Admin-Seite zeigt alle gespeicherten Produkte als WP_List_Table

### Editor-Erweiterung

- [ ] **EDIT-01**: `amazon:last` Token wird im Editor live zum letzten Telegram-Produkt aufgeloest
- [ ] **EDIT-02**: `amazon:lastN` Token (last2, last3 etc.) wird zum N-t-letzten Produkt aufgeloest
- [ ] **EDIT-03**: Affiliate Card Block hat Dropdown-Picker mit gespeicherten Produkten (neueste oben)
- [ ] **EDIT-04**: Dropdown-Auswahl hydrated den Block identisch zu manueller ASIN-Eingabe

### Tracking-ID Registry

- [x] **TRID-01**: Plugin speichert alle verfuegbaren Tracking-IDs in DB (eigene Tabelle)
- [x] **TRID-02**: Bestehende Tracking-IDs koennen per Backfill importiert werden (~170 IDs)
- [x] **TRID-03**: Bot warnt per Telegram wenn ein Post-Datum keine passende Tracking-ID hat
- [x] **TRID-04**: User kann per Telegram-Antwort (done/ok/angelegt) eine neue Tracking-ID als verfuegbar markieren

### Settings

- [x] **SETT-01**: Bot-Token ist in Plugin-Settings konfigurierbar
- [x] **SETT-02**: Chat-ID ist als optionales Feld in Plugin-Settings konfigurierbar
- [x] **SETT-03**: Webhook-Status (aktiv/inaktiv) wird in Settings angezeigt

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Produkt-Bibliothek

- **PLIB-05**: Produkt-Suche in Bibliothek nach Titel
- **PLIB-06**: Produkte manuell loeschen oder bearbeiten in Admin-Seite
- **PLIB-07**: Bulk-Import von ASINs in Bibliothek

### Telegram Bot

- **TGBOT-08**: Bot-Commands (/start, /help, /status)
- **TGBOT-09**: Automatische Webhook-Registrierung bei Telegram aus Settings heraus

## Out of Scope

| Feature | Reason |
|---------|--------|
| Multi-User Telegram Support | Single-User Workflow, kein Bedarf |
| Telegram Bot Commands (/start etc.) | Overkill fuer Single-User Bot |
| Produkt-Suche nach Titel | ASIN-basierter Workflow reicht fuer v1.0 |
| Webhook Auto-Registration | Manuelles Setup via curl/Browser genuegt |
| Polling-Modus | WordPress kann kein Long-Polling, Webhook ist besser |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| TGBOT-01 | Phase 1 | Complete |
| TGBOT-02 | Phase 1 | Complete |
| TGBOT-03 | Phase 1 | Complete |
| TGBOT-04 | Phase 1 | Complete |
| TGBOT-05 | Phase 1 | Complete |
| TGBOT-06 | Phase 1 | Complete |
| TGBOT-07 | Phase 1 | Complete |
| PLIB-01 | Phase 2 | Complete |
| PLIB-02 | Phase 2 | Complete |
| PLIB-03 | Phase 2 | Complete |
| PLIB-04 | Phase 4 | Pending |
| EDIT-01 | Phase 3 | Pending |
| EDIT-02 | Phase 3 | Pending |
| EDIT-03 | Phase 4 | Pending |
| EDIT-04 | Phase 4 | Pending |
| TRID-01 | Phase 2 | Complete |
| TRID-02 | Phase 2 | Complete |
| TRID-03 | Phase 1 | Complete |
| TRID-04 | Phase 1 | Complete |
| SETT-01 | Phase 1 | Complete |
| SETT-02 | Phase 1 | Complete |
| SETT-03 | Phase 1 | Complete |

**Coverage:**
- v1.0 requirements: 22 total
- Mapped to phases: 22
- Unmapped: 0

---
*Requirements defined: 2026-03-25*
*Last updated: 2026-03-25 after adding Tracking-ID Registry requirements (TRID-01..04)*
