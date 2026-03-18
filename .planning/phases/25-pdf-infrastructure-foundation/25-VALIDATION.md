---
phase: 25
slug: pdf-infrastructure-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-18
---

# Phase 25 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (backend) + Playwright 1.50 (E2E) |
| **Config file** | `phpunit.xml` / `playwright.config.ts` |
| **Quick run command** | `php vendor/bin/phpunit --filter ResolutionDocument` |
| **Full suite command** | `php vendor/bin/phpunit && npx playwright test` |
| **Estimated runtime** | ~30 seconds (PHPUnit) + ~60 seconds (Playwright) |

---

## Sampling Rate

- **After every task commit:** Run `php vendor/bin/phpunit --filter ResolutionDocument`
- **After every plan wave:** Run `php vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 25-01-01 | 01 | 1 | PDF-01 | unit | `php vendor/bin/phpunit --filter ResolutionDocumentRepository` | ❌ W0 | ⬜ pending |
| 25-01-02 | 01 | 1 | PDF-02 | unit | `php vendor/bin/phpunit --filter ResolutionDocumentController` | ❌ W0 | ⬜ pending |
| 25-01-03 | 01 | 1 | PDF-03 | unit | `php vendor/bin/phpunit --filter ResolutionDocumentServe` | ❌ W0 | ⬜ pending |
| 25-01-04 | 01 | 1 | PDF-04 | integration | `grep -q AGVOTE_UPLOAD_DIR app/Controller/MeetingAttachmentController.php` | ✅ | ⬜ pending |
| 25-02-01 | 02 | 2 | PDF-06 | e2e | `npx playwright test --grep "filepond upload"` | ❌ W0 | ⬜ pending |
| 25-02-02 | 02 | 2 | PDF-07 | unit | `grep -q "ag-pdf-viewer" public/assets/js/components/ag-pdf-viewer.js` | ❌ W0 | ⬜ pending |
| 25-03-01 | 03 | 3 | PDF-08 | e2e | `npx playwright test --grep "resolution pdf"` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/ResolutionDocumentControllerTest.php` — stubs for PDF-01, PDF-02, PDF-03
- [ ] `tests/Unit/ResolutionDocumentRepositoryTest.php` — stubs for PDF-01

*Existing MeetingAttachmentControllerTest.php provides pattern reference.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| PDF renders correctly in iframe | PDF-07 | Visual rendering quality | Upload a PDF, open ag-pdf-viewer, verify text is readable |
| Bottom sheet UX on mobile | PDF-08 | Touch interaction testing | Open voter view on 375px viewport, tap "Consulter le document", verify slide-up |
| Docker volume persistence | PDF-05 | Requires container restart | Upload PDF, restart container, verify PDF still accessible |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
