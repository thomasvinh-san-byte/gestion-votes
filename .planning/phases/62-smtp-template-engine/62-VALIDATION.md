---
phase: 62
slug: smtp-template-engine
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-01
---

# Phase 62 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php vendor/bin/phpunit --filter "Settings\|EmailTemplate\|Mailer"` |
| **Full suite command** | `php vendor/bin/phpunit` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php vendor/bin/phpunit --filter "Settings\|EmailTemplate\|Mailer"`
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 62-01-01 | 01 | 1 | EMAIL-05 | unit + grep | `php vendor/bin/phpunit --filter SettingsControllerTest` | ✅ | ⬜ pending |
| 62-01-02 | 01 | 1 | EMAIL-05 | unit | `php vendor/bin/phpunit --filter MailerServiceTest` | ✅ | ⬜ pending |
| 62-02-01 | 02 | 1 | EMAIL-04 | unit | `php vendor/bin/phpunit --filter EmailTemplatesControllerTest` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test files needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| SMTP test email arrives in inbox | EMAIL-05 | Requires real SMTP server | Configure SMTP in settings, click "Envoyer un test", verify email arrives |
| Template preview renders in browser | EMAIL-04 | Visual rendering | Edit template, click preview, verify HTML renders with sample data |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 15s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved
