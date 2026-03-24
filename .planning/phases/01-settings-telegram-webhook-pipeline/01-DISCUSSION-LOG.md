# Phase 1: Settings + Telegram Webhook Pipeline - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-25
**Phase:** 01-settings-telegram-webhook-pipeline
**Areas discussed:** Bot-Antwort Format, Tracking-ID State, Tracking-ID Warnung, Settings-UI Layout

---

## Bot-Antwort Format

| Option | Description | Selected |
|--------|-------------|----------|
| Nur URL (wie bisher) | Blanke Affiliate-URL -- minimal, copy-paste-freundlich | |
| URL + Kontext | URL plus ASIN und aktuelle Tracking-ID als Info-Zeile | |
| Du entscheidest | Claude waehlt ein sinnvolles Format | ✓ |

**User's choice:** Du entscheidest
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Deutsch (wie bisher) | Fehlermeldungen auf Deutsch, konsistent mit Node-RED Bot | ✓ |
| Du entscheidest | Claude waehlt passend zum Kontext | |

**User's choice:** Deutsch (wie bisher)
**Notes:** User wants differentiated error messages per failure case. Also wants research into additional useful error cases beyond Node-RED's current set.

---

## Tracking-ID State

| Option | Description | Selected |
|--------|-------------|----------|
| wp_options (einfach) | Zwei Eintraege in wp_options | |
| Produkt-Tabelle (Phase 2) | lastAsin aus Bibliothek, nur trackingId in wp_options | |
| Du entscheidest | Claude waehlt die sinnvollste Loesung | ✓ |

**User's choice:** Du entscheidest
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Erhalten bleiben | Tracking-ID ueberlebt Plugin-Updates und Neustarts | ✓ |
| Zuruecksetzen | Bei Neustart immer Default-Tracking-ID | |

**User's choice:** Erhalten bleiben
**Notes:** None

---

## Tracking-ID Warnung

| Option | Description | Selected |
|--------|-------------|----------|
| Bei jedem Produkt-Link | Pruefen bei jeder ASIN/URL | |
| Nur bei Datum-Wechsel | Pruefen wenn User Datum schickt | |
| Beides | Bei Datum-Wechsel UND Produkt-Links | |

**User's choice:** Du entscheidest (freeform)
**Notes:** "kein plan. entscheide du wie es am sinnvollsten ist"

| Option | Description | Selected |
|--------|-------------|----------|
| Aktuelle ID speichern | Bot speichert aktive Tracking-ID in Registry | |
| ID + Datum speichern | Bot speichert Tracking-ID UND Datum | |
| Du entscheidest | Claude designt den sinnvollsten Flow | ✓ |

**User's choice:** Du entscheidest
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Einmal pro ID | Warnung nur beim ersten Mal | |
| Jedes Mal | Bei jeder Nachricht warnen | |
| Du entscheidest | Claude waehlt sinnvolles Verhalten | ✓ |

**User's choice:** Du entscheidest
**Notes:** None

---

## Settings-UI Layout

| Option | Description | Selected |
|--------|-------------|----------|
| Eigener Tab | Neuer Tab "Telegram Bot" neben Einstellungen und Audit | ✓ |
| In Einstellungen | Neue Sektion im bestehenden Einstellungen-Tab | |
| Du entscheidest | Claude waehlt das sinnvollste Layout | |

**User's choice:** Eigener Tab
**Notes:** None

| Option | Description | Selected |
|--------|-------------|----------|
| Einfacher Indikator | Gruen/Rot Badge | |
| Live-Check | Button der getWebhookInfo aufruft | |
| Beides | Badge + optionaler Live-Check Button | |

**User's choice:** Du entscheidest (freeform)
**Notes:** "kein plan. du entscheidest"

---

## Claude's Discretion

- Bot response format (URL + context details)
- Error message design and additional error cases
- Tracking-ID state storage approach
- Warning trigger logic and frequency
- done/ok/angelegt confirmation flow
- Webhook status display

## Deferred Ideas

None
