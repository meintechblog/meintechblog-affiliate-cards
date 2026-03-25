---
phase: 1
slug: settings-telegram-webhook-pipeline
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-25
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Vitest 3.2.4 (existing) + PHP unit tests |
| **Config file** | `vitest.config.ts` (existing), `tests/` for PHP |
| **Quick run command** | `pnpm vitest run --reporter=verbose` |
| **Full suite command** | `pnpm vitest run && php tests/run-tests.php` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `pnpm vitest run --reporter=verbose`
- **After every plan wave:** Run full suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 1-01-01 | 01 | 1 | SETT-01 | unit | `php tests/test-settings.php` | ❌ W0 | ⬜ pending |
| 1-01-02 | 01 | 1 | SETT-02 | unit | `php tests/test-settings.php` | ❌ W0 | ⬜ pending |
| 1-01-03 | 01 | 1 | SETT-03 | manual | N/A (UI indicator) | N/A | ⬜ pending |
| 1-02-01 | 02 | 1 | TGBOT-01 | unit | `php tests/test-telegram-handler.php` | ❌ W0 | ⬜ pending |
| 1-02-02 | 02 | 1 | TGBOT-03 | unit | `php tests/test-url-resolver.php` | ❌ W0 | ⬜ pending |
| 1-02-03 | 02 | 1 | TGBOT-04 | unit | `php tests/test-telegram-handler.php` | ❌ W0 | ⬜ pending |
| 1-02-04 | 02 | 1 | TGBOT-05 | unit | `php tests/test-telegram-handler.php` | ❌ W0 | ⬜ pending |
| 1-02-05 | 02 | 1 | TGBOT-06 | unit | `php tests/test-telegram-handler.php` | ❌ W0 | ⬜ pending |
| 1-02-06 | 02 | 1 | TGBOT-07 | unit | `php tests/test-telegram-handler.php` | ❌ W0 | ⬜ pending |
| 1-03-01 | 03 | 2 | TRID-03 | unit | `php tests/test-tracking-registry.php` | ❌ W0 | ⬜ pending |
| 1-03-02 | 03 | 2 | TRID-04 | unit | `php tests/test-tracking-registry.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/test-settings.php` — stubs for SETT-01, SETT-02
- [ ] `tests/test-telegram-handler.php` — stubs for TGBOT-01..07
- [ ] `tests/test-url-resolver.php` — stubs for TGBOT-03
- [ ] `tests/test-tracking-registry.php` — stubs for TRID-03, TRID-04

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Settings page displays Telegram Bot tab | SETT-03 | UI rendering requires browser | Navigate to Settings > Affiliate Card > Telegram Bot tab, verify green/red indicator |
| Telegram webhook receives real message | TGBOT-02 | Requires live Telegram bot | Send ASIN to bot, verify reply in Telegram |
| ShortURL resolution follows redirects | TGBOT-03 | Requires external HTTP call | Send amzn.to link to bot, verify resolved URL |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
