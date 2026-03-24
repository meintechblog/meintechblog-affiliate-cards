# meintechblog Affiliate Cards

## What This Is

Ein WordPress-Plugin, das Amazon-Affiliate-Produkte als Rich Cards im Gutenberg-Editor darstellt. Produkte werden per Telegram-Bot empfangen, in einer Bibliothek gespeichert und per Shortcodes oder Dropdown im Editor eingefuegt. Ersetzt den bisherigen Node-RED Telegram Bot Flow komplett.

## Core Value

Affiliate-Produkte muessen in unter 30 Sekunden von der Telegram-Nachricht in einen WordPress-Blogpost eingefuegt werden koennen — schneller als jeder manuelle Workflow.

## Current Milestone: v1.0 Telegram-to-WordPress Affiliate Pipeline

**Goal:** Node-RED Telegram Bot komplett ins WordPress Plugin portieren — Amazon-Links per Telegram empfangen, verarbeiten, in Produkt-Bibliothek speichern und direkt im Gutenberg-Editor ueber Shortcuts und Dropdown einfuegen.

**Target features:**
- Telegram Webhook-Empfang und Bot-Antworten direkt aus WordPress
- ShortURL-Aufloesung (amzn.to / amzn.eu) und ASIN-Extraktion
- Tracking-ID Management (heute, YYMMDD, DD.MM.YY, reset)
- Produkt-Bibliothek (Custom DB Table) mit allen per Telegram empfangenen Produkten
- `amazon:last` / `amazon:last2` / `amazon:lastN` Token-Support im Editor
- Dropdown-Picker in Affiliate Card Block (gespeicherte Produkte, neueste oben)
- Bot-Token & Chat-ID konfigurierbar in Plugin-Settings

## Requirements

### Validated

<!-- Shipped and confirmed valuable. Existing plugin capabilities. -->

- Affiliate Card Gutenberg Block mit Server-Side Rendering
- `amazon:ASIN` Token-Replacement im Editor (live + save hook)
- Amazon Creators API Integration (OAuth 2.0, ASIN lookup)
- Partner-Tag Ableitung aus Post-Datum (`meintechblog-YYMMDD-21`)
- Affiliate Audit System (Post-Scanning, Status-Tracking)
- Plugin Settings UI (CTA Label, Badge Mode, Marketplace, API Credentials)

### Active

<!-- Current scope. Building toward these in v1.0. -->

- [ ] Telegram Bot Integration (Webhook, Message Processing, Response)
- [ ] Produkt-Bibliothek (DB Storage, CRUD)
- [ ] Editor Token-Erweiterung (`amazon:last`, `amazon:lastN`)
- [ ] Dropdown-Picker fuer gespeicherte Produkte im Block
- [ ] Bot-Token & Chat-ID in Plugin-Settings

### Out of Scope

<!-- Explicit boundaries. -->

- Telegram Bot Commands (/start, /help etc.) — overkill fuer Single-User Bot
- Produkt-Suche in der Bibliothek nach Titel — ASIN reicht erstmal
- Multi-User Support (mehrere Chat-IDs) — Single-User Workflow
- Webhook Auto-Registration bei Telegram — manuelles Setup reicht

## Context

- Bestehendes WordPress Plugin (v0.2.30) mit vollstaendiger Affiliate Card Pipeline
- Node-RED Flow wird aktuell fuer Telegram Bot genutzt, soll komplett abgeloest werden
- Telegram Bot Token: ueber @BotFather erstellt, wird in Plugin-Settings gespeichert
- Amazon Creators API Client existiert bereits mit OAuth 2.0 und ASIN-Lookup
- Partner-Tag Format identisch in Node-RED und Plugin: `meintechblog-YYMMDD-21`
- REST API Infrastruktur vorhanden (`class-mtb-affiliate-rest-controller.php`)
- Block Token-Replacement (`amazon:ASIN`) funktioniert bereits live im Editor

## Constraints

- **Platform**: WordPress Plugin, PHP 8.0+, keine externen Dependencies
- **Database**: WordPress Custom Tables via `$wpdb` (kein ORM)
- **Telegram API**: Bot API via HTTPS, Webhook-Modus (kein Polling)
- **Security**: Bot-Token darf nie im Frontend/Client-Code landen
- **Compatibility**: Muss mit bestehendem Plugin-Code koexistieren (keine Breaking Changes)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Webhook statt Polling | WordPress kann kein Long-Polling, Webhook ist serverless-freundlich | -- Pending |
| Custom Table statt Post Meta | Produkte sind eigenstaendige Entitaeten, nicht an Posts gebunden | -- Pending |
| Chat-ID als optionaler Filter | Security: verhindert fremde Bot-Nachrichten in Bibliothek | -- Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check -- still the right priority?
3. Audit Out of Scope -- reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-03-25 after milestone v1.0 initialization*
