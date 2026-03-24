# Requirements: meintechblog Affiliate Cards

**Defined:** 2026-03-25
**Core Value:** Affiliate-Produkte in unter 30 Sekunden von Telegram-Nachricht in WordPress-Blogpost

## v1.0 Requirements

Requirements for Telegram-to-WordPress Affiliate Pipeline. Each maps to roadmap phases.

### Telegram Bot

- [ ] **TGBOT-01**: Plugin empfaengt Telegram-Nachrichten via Webhook und validiert Secret-Token
- [ ] **TGBOT-02**: Bot antwortet mit fertigem Affiliate-Link (ASIN + aktuelle Tracking-ID)
- [ ] **TGBOT-03**: Bot loest ShortURLs auf (amzn.to / amzn.eu) und extrahiert ASIN
- [ ] **TGBOT-04**: Bot akzeptiert direkte ASINs (B0XXXXXXXX) und Amazon-Produkt-URLs
- [ ] **TGBOT-05**: User kann Tracking-ID per Datum setzen (heute, YYMMDD, DD.MM.YY/YYYY)
- [ ] **TGBOT-06**: User kann Tracking-ID per "reset" auf Default zuruecksetzen
- [ ] **TGBOT-07**: Bot filtert optional nach Chat-ID (konfigurierbar in Settings)

### Produkt-Bibliothek

- [ ] **PLIB-01**: Empfangene Produkte werden automatisch in Custom DB Table gespeichert
- [ ] **PLIB-02**: Produkt-Eintrag enthaelt ASIN, Titel, Detail-URL, Bild-URL, Empfangsdatum
- [ ] **PLIB-03**: REST API Endpoint liefert gespeicherte Produkte (sortiert nach Datum, neueste zuerst)
- [ ] **PLIB-04**: Admin-Seite zeigt alle gespeicherten Produkte als WP_List_Table

### Editor-Erweiterung

- [ ] **EDIT-01**: `amazon:last` Token wird im Editor live zum letzten Telegram-Produkt aufgeloest
- [ ] **EDIT-02**: `amazon:lastN` Token (last2, last3 etc.) wird zum N-t-letzten Produkt aufgeloest
- [ ] **EDIT-03**: Affiliate Card Block hat Dropdown-Picker mit gespeicherten Produkten (neueste oben)
- [ ] **EDIT-04**: Dropdown-Auswahl hydrated den Block identisch zu manueller ASIN-Eingabe

### Settings

- [ ] **SETT-01**: Bot-Token ist in Plugin-Settings konfigurierbar
- [ ] **SETT-02**: Chat-ID ist als optionales Feld in Plugin-Settings konfigurierbar
- [ ] **SETT-03**: Webhook-Status (aktiv/inaktiv) wird in Settings angezeigt

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
| TGBOT-01 | -- | Pending |
| TGBOT-02 | -- | Pending |
| TGBOT-03 | -- | Pending |
| TGBOT-04 | -- | Pending |
| TGBOT-05 | -- | Pending |
| TGBOT-06 | -- | Pending |
| TGBOT-07 | -- | Pending |
| PLIB-01 | -- | Pending |
| PLIB-02 | -- | Pending |
| PLIB-03 | -- | Pending |
| PLIB-04 | -- | Pending |
| EDIT-01 | -- | Pending |
| EDIT-02 | -- | Pending |
| EDIT-03 | -- | Pending |
| EDIT-04 | -- | Pending |
| SETT-01 | -- | Pending |
| SETT-02 | -- | Pending |
| SETT-03 | -- | Pending |

**Coverage:**
- v1.0 requirements: 18 total
- Mapped to phases: 0
- Unmapped: 18

---
*Requirements defined: 2026-03-25*
*Last updated: 2026-03-25 after initial definition*
